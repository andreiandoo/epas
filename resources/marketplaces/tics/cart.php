<?php
/**
 * TICS.ro - Shopping Cart Page
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = 'Coșul tău';
$pageDescription = 'Finalizează comanda și cumpără bilete pentru evenimentele tale preferate.';
$hideCategoriesBar = true;
$bodyClass = 'bg-gray-50';

// Head extra styles for cart page
$headExtra = <<<HTML
<style>
    @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
    @keyframes scaleIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; }
    .animate-pulse { animation: pulse 2s ease-in-out infinite; }
    .animate-shake { animation: shake 0.4s ease; }
    .animate-scaleIn { animation: scaleIn 0.3s ease forwards; }

    .cart-item { transition: all 0.3s ease; }
    .cart-item:hover { background: #f9fafb; }

    .qty-btn { transition: all 0.2s ease; }
    .qty-btn:hover { background: #111827; color: white; }
    .qty-btn:active { transform: scale(0.9); }

    .promo-input:focus { box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1); }

    .checkout-btn {
        background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        transition: all 0.3s ease;
    }
    .checkout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.3);
    }

    .suggestion-card { transition: all 0.3s ease; }
    .suggestion-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1); }
</style>
HTML;

include __DIR__ . '/includes/head.php';
?>

<!-- Custom Cart Header with Progress Steps -->
<header class="sticky top-0 z-40 bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <a href="/" class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">T</span>
                </div>
                <span class="font-bold text-lg">TICS</span>
            </a>

            <!-- Progress Steps -->
            <div class="hidden md:flex items-center gap-2">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center text-sm font-bold">1</div>
                    <span class="text-sm font-medium text-gray-900">Coș</span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">2</div>
                    <span class="text-sm text-gray-500">Plată</span>
                </div>
                <div class="w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-sm font-medium">3</div>
                    <span class="text-sm text-gray-500">Confirmare</span>
                </div>
            </div>

            <a href="/evenimente" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">← Continuă cumpărăturile</a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Cart Items -->
        <div class="flex-1">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Coșul tău</h1>
                <span class="text-gray-500" id="itemCount">0 articole</span>
            </div>

            <!-- Timer Warning -->
            <div class="bg-gradient-to-r from-amber-500 to-orange-500 rounded-xl p-4 mb-6 flex items-center gap-3 text-white animate-fadeInUp">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold">Biletele sunt rezervate pentru tine!</p>
                    <p class="text-sm text-white/80">Finalizează comanda în timpul rămas</p>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold" id="timer">14:59</div>
                    <div class="text-xs text-white/70">minute</div>
                </div>
            </div>

            <!-- Cart Items List -->
            <div class="space-y-4" id="cartItemsList">
                <!-- Items loaded dynamically or empty state -->
                <div id="emptyCart" class="hidden bg-white rounded-2xl border border-gray-200 p-8 text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Coșul tău este gol</h3>
                    <p class="text-gray-500 mb-6">Adaugă bilete la evenimentele tale preferate pentru a continua.</p>
                    <a href="/evenimente" class="inline-block px-6 py-3 bg-gray-900 text-white font-medium rounded-xl hover:bg-gray-800 transition-colors">
                        Descoperă evenimente
                    </a>
                </div>
            </div>

            <!-- Suggestions Section - Changes based on login status -->
            <div class="mt-10 animate-fadeInUp" style="animation-delay: 0.4s" id="suggestionsSection">
                <!-- AI Suggestions Header (shown when logged in) -->
                <div id="aiSuggestionsHeader" class="flex items-center gap-2 mb-4 hidden">
                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900">Ți-ar putea plăcea</h3>
                    <span class="px-2 py-0.5 bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-700 text-xs font-medium rounded-full">AI</span>
                </div>
                <!-- Generic Suggestions Header (shown when not logged in) -->
                <div id="genericSuggestionsHeader" class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 bg-gray-900 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-900">Îți recomandăm și următoarele evenimente</h3>
                </div>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" id="suggestionsList">
                    <!-- Suggestions loaded dynamically -->
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="lg:w-[400px]">
            <div class="sticky top-24">
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xl animate-fadeInUp" style="animation-delay: 0.3s">
                    <div class="p-5 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                        <h2 class="text-lg font-bold text-gray-900">Sumar comandă</h2>
                    </div>

                    <div class="p-5">
                        <!-- Promo Code -->
                        <div class="mb-5">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cod promoțional</label>
                            <div class="flex gap-2">
                                <input type="text" id="promoInput" placeholder="Introdu codul" class="promo-input flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:border-indigo-500 transition-all text-sm">
                                <button onclick="applyPromo()" class="px-5 py-2.5 bg-gray-900 text-white font-medium rounded-xl hover:bg-gray-800 transition-colors text-sm">
                                    Aplică
                                </button>
                            </div>
                            <div id="promoSuccess" class="hidden mt-2 flex items-center gap-2 text-green-600 text-sm animate-fadeInUp">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span id="promoSuccessText">Cod aplicat: -10%</span>
                            </div>
                            <div id="promoError" class="hidden mt-2 flex items-center gap-2 text-red-500 text-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                Cod invalid sau expirat
                            </div>
                        </div>

                        <!-- Points -->
                        <div class="p-4 bg-gradient-to-r from-amber-50 to-orange-50 rounded-xl border border-amber-100 mb-5">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">🎁</span>
                                    <span class="font-medium text-gray-900">Punctele tale</span>
                                </div>
                                <span class="font-bold text-amber-600" id="userPoints">0 pt</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button onclick="usePoints(500, 50)" class="points-btn py-2.5 bg-white border border-amber-200 rounded-xl text-sm font-medium hover:bg-amber-100 transition-colors" data-points="500">
                                    -50 RON <span class="text-xs text-gray-500">(500pt)</span>
                                </button>
                                <button onclick="usePoints(1000, 100)" class="points-btn py-2.5 bg-white border border-amber-200 rounded-xl text-sm font-medium hover:bg-amber-100 transition-colors" data-points="1000">
                                    -100 RON <span class="text-xs text-gray-500">(1000pt)</span>
                                </button>
                            </div>
                            <div id="pointsApplied" class="hidden mt-3 flex items-center justify-between">
                                <span class="text-sm text-green-600 font-medium">✓ Reducere aplicată</span>
                                <button onclick="clearPoints()" class="text-xs text-gray-500 hover:text-gray-700">Anulează</button>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-3 mb-5">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotal (<span id="ticketCount">0</span> bilete)</span>
                                <span class="text-gray-900" id="subtotal">0 RON</span>
                            </div>
                            <div id="promoDiscount" class="hidden flex justify-between text-sm">
                                <span class="text-green-600">Reducere cod</span>
                                <span class="text-green-600" id="promoAmount">-0 RON</span>
                            </div>
                            <div id="pointsDiscount" class="hidden flex justify-between text-sm">
                                <span class="text-amber-600">Reducere puncte</span>
                                <span class="text-amber-600" id="pointsAmount">-0 RON</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Taxe și comisioane</span>
                                <span class="text-green-600 font-medium">Incluse ✓</span>
                            </div>
                            <div class="pt-4 border-t border-gray-200 flex justify-between items-center">
                                <span class="font-semibold text-gray-900">Total de plată</span>
                                <span class="text-3xl font-bold text-gray-900" id="totalPrice">0 RON</span>
                            </div>
                            <p class="text-xs text-gray-400 text-right">sau de la <strong id="monthlyPrice">0</strong> RON/lună în 6 rate</p>
                        </div>

                        <!-- Earn Points Info -->
                        <div class="flex items-center gap-3 p-3 bg-green-50 rounded-xl mb-5 border border-green-100">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <span>🎁</span>
                            </div>
                            <span class="text-sm text-green-800">Câștigi <strong id="earnPoints">0</strong> puncte cu această comandă!</span>
                        </div>

                        <!-- Checkout Button -->
                        <a href="/checkout" id="checkoutBtn" class="checkout-btn block w-full py-4 text-white font-bold rounded-xl text-center text-lg shadow-lg">
                            Continuă către plată →
                        </a>

                        <!-- Trust Badges -->
                        <div class="mt-5 flex items-center justify-center gap-4 text-gray-400 text-xs">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                SSL Securizat
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Bilete garantate
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="mt-4 bg-white rounded-xl border border-gray-200 p-4">
                    <p class="text-xs text-gray-500 mb-3">Metode de plată acceptate</p>
                    <div class="flex items-center gap-3 flex-wrap">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa" class="h-6 opacity-60">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" class="h-6 opacity-60">
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs text-gray-500">Apple Pay</span>
                        <span class="px-2 py-1 bg-gray-100 rounded text-xs text-gray-500">Google Pay</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Footer Mini -->
<footer class="bg-white border-t border-gray-200 mt-16 py-6">
    <div class="max-w-7xl mx-auto px-4 lg:px-8">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-gray-500">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-gray-900 rounded flex items-center justify-center">
                    <span class="text-white font-bold text-xs">T</span>
                </div>
                <span>© <?= date('Y') ?> TICS.ro • Powered by Tixello</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="/termeni" class="hover:text-gray-900 transition-colors">Termeni</a>
                <a href="/confidentialitate" class="hover:text-gray-900 transition-colors">Confidențialitate</a>
                <a href="/ajutor" class="hover:text-gray-900 transition-colors">Ajutor</a>
            </div>
        </div>
    </div>
</footer>

<script src="<?= asset('assets/js/utils.js') ?>"></script>
<script src="<?= asset('assets/js/api.js') ?>"></script>
<script>
    // Cart State
    let state = {
        items: [],
        subtotal: 0,
        promoDiscount: 0,
        pointsDiscount: 0,
        pointsUsed: 0,
        userPoints: 1250
    };

    // Timer countdown
    let timeLeft = 15 * 60;
    function updateTimer() {
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        document.getElementById('timer').textContent =
            `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        if (timeLeft > 0) {
            timeLeft--;
            setTimeout(updateTimer, 1000);
        } else {
            // Timer expired - redirect or clear cart
            alert('Timpul a expirat. Biletele au fost eliberate.');
            window.location.href = '/evenimente';
        }
    }

    // Initialize cart from localStorage
    function initCart() {
        const savedCart = localStorage.getItem('tics_cart');
        if (savedCart) {
            state.items = JSON.parse(savedCart);
            renderCartItems();
        } else {
            // Demo items for development
            state.items = [
                {
                    id: 1,
                    name: 'Coldplay: Music of the Spheres',
                    venue: 'Arena Națională, București',
                    date: '14 Feb 2026',
                    time: '20:00',
                    image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=200&h=200&fit=crop',
                    ticketType: 'General Admission',
                    price: 349,
                    quantity: 2,
                    slug: 'coldplay-music-of-the-spheres'
                },
                {
                    id: 2,
                    name: 'Micutzu - Sold Out Tour',
                    venue: 'Sala Palatului, București',
                    date: '22 Ian 2026',
                    time: '19:30',
                    image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=200&h=200&fit=crop',
                    ticketType: 'Categoria I • R5, L12',
                    price: 89,
                    quantity: 1,
                    slug: 'micutzu-sold-out-tour'
                }
            ];
            renderCartItems();
        }

        document.getElementById('userPoints').textContent = state.userPoints.toLocaleString() + ' pt';
        updateTimer();
        loadSuggestions();
    }

    // Render cart items
    function renderCartItems() {
        const container = document.getElementById('cartItemsList');
        const emptyCart = document.getElementById('emptyCart');

        if (state.items.length === 0) {
            container.innerHTML = '';
            emptyCart.classList.remove('hidden');
            document.getElementById('suggestionsSection').classList.add('hidden');
            document.getElementById('checkoutBtn').classList.add('pointer-events-none', 'opacity-50');
            return;
        }

        emptyCart.classList.add('hidden');
        document.getElementById('suggestionsSection').classList.remove('hidden');
        document.getElementById('checkoutBtn').classList.remove('pointer-events-none', 'opacity-50');

        container.innerHTML = state.items.map((item, index) => `
            <div class="cart-item bg-white rounded-2xl border border-gray-200 p-5 animate-fadeInUp" data-id="${item.id}" data-price="${item.price}" style="animation-delay: ${index * 0.1}s">
                <div class="flex gap-4">
                    <div class="relative flex-shrink-0">
                        <img src="${item.image}" class="w-24 h-24 sm:w-32 sm:h-32 rounded-xl object-cover">
                        <span class="absolute -top-2 -right-2 w-6 h-6 bg-indigo-600 text-white text-xs font-bold rounded-full flex items-center justify-center item-badge">${item.quantity}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <a href="/bilete/${item.slug}" class="font-semibold text-gray-900 mb-1 hover:text-indigo-600 transition-colors">${item.name}</a>
                                <p class="text-sm text-gray-500 mb-2">${item.venue}</p>
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <span class="px-2 py-1 bg-gray-100 rounded-lg text-xs text-gray-600">📅 ${item.date}</span>
                                    <span class="px-2 py-1 bg-gray-100 rounded-lg text-xs text-gray-600">🕐 ${item.time}</span>
                                </div>
                                <span class="inline-block px-3 py-1 bg-indigo-50 text-indigo-700 text-sm font-medium rounded-lg">${item.ticketType}</span>
                            </div>
                            <button class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" onclick="removeItem(${item.id})">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-3 bg-gray-100 rounded-full p-1">
                                <button class="qty-btn w-8 h-8 rounded-full border border-gray-300 bg-white flex items-center justify-center font-bold" onclick="changeQty(${item.id}, -1)">−</button>
                                <span class="w-8 text-center font-bold qty-value">${item.quantity}</span>
                                <button class="qty-btn w-8 h-8 rounded-full border border-gray-300 bg-white flex items-center justify-center font-bold" onclick="changeQty(${item.id}, 1)">+</button>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-400 price-detail">${item.price} RON × ${item.quantity}</p>
                                <p class="text-xl font-bold text-gray-900 item-total">${(item.price * item.quantity).toLocaleString()} RON</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        recalculateSubtotal();
    }

    // Calculate totals from cart items
    function recalculateSubtotal() {
        let subtotal = 0;
        let ticketCount = 0;
        state.items.forEach(item => {
            subtotal += item.price * item.quantity;
            ticketCount += item.quantity;
        });
        state.subtotal = subtotal;
        document.getElementById('subtotal').textContent = subtotal.toLocaleString() + ' RON';
        document.getElementById('ticketCount').textContent = ticketCount;
        document.getElementById('itemCount').textContent = ticketCount + ' articole';
        updateTotals();
    }

    // Quantity change - updates UI without full re-render for smooth UX
    function changeQty(itemId, delta) {
        const item = state.items.find(i => i.id === itemId);
        if (!item) return;

        const newQty = Math.max(1, Math.min(10, item.quantity + delta));
        if (newQty === item.quantity) return; // No change

        item.quantity = newQty;
        saveCart();

        // Update UI elements directly for this item
        const itemEl = document.querySelector(`[data-id="${itemId}"]`);
        if (itemEl) {
            // Update badge
            const badge = itemEl.querySelector('.item-badge');
            if (badge) badge.textContent = newQty;

            // Update quantity display
            const qtyValue = itemEl.querySelector('.qty-value');
            if (qtyValue) qtyValue.textContent = newQty;

            // Update price detail (price × qty)
            const priceDetail = itemEl.querySelector('.price-detail');
            if (priceDetail) priceDetail.textContent = `${item.price} RON × ${newQty}`;

            // Update item total with animation
            const itemTotal = itemEl.querySelector('.item-total');
            if (itemTotal) {
                const total = item.price * newQty;
                itemTotal.textContent = total.toLocaleString() + ' RON';
                itemTotal.classList.add('animate-scaleIn');
                setTimeout(() => itemTotal.classList.remove('animate-scaleIn'), 300);
            }
        }

        // Recalculate totals
        recalculateSubtotal();
    }

    // Remove item
    function removeItem(itemId) {
        const itemEl = document.querySelector(`[data-id="${itemId}"]`);
        if (itemEl) {
            itemEl.style.transform = 'translateX(100%)';
            itemEl.style.opacity = '0';
        }
        setTimeout(() => {
            state.items = state.items.filter(i => i.id !== itemId);
            saveCart();
            renderCartItems();
        }, 300);
    }

    // Save cart to localStorage
    function saveCart() {
        localStorage.setItem('tics_cart', JSON.stringify(state.items));
    }

    // Apply promo
    function applyPromo() {
        const code = document.getElementById('promoInput').value.trim().toUpperCase();
        const successEl = document.getElementById('promoSuccess');
        const errorEl = document.getElementById('promoError');

        successEl.classList.add('hidden');
        errorEl.classList.add('hidden');

        if (code === 'WELCOME10' || code === 'TICS10') {
            state.promoDiscount = Math.round(state.subtotal * 0.1);
            document.getElementById('promoSuccessText').textContent = `Cod aplicat: -10% (-${state.promoDiscount} RON)`;
            successEl.classList.remove('hidden');
            document.getElementById('promoDiscount').classList.remove('hidden');
            document.getElementById('promoAmount').textContent = `-${state.promoDiscount} RON`;
            document.getElementById('promoInput').disabled = true;
            document.getElementById('promoInput').classList.add('bg-green-50', 'border-green-300');
            updateTotals();
        } else if (code === 'TICS20') {
            state.promoDiscount = Math.round(state.subtotal * 0.2);
            document.getElementById('promoSuccessText').textContent = `Cod aplicat: -20% (-${state.promoDiscount} RON)`;
            successEl.classList.remove('hidden');
            document.getElementById('promoDiscount').classList.remove('hidden');
            document.getElementById('promoAmount').textContent = `-${state.promoDiscount} RON`;
            document.getElementById('promoInput').disabled = true;
            document.getElementById('promoInput').classList.add('bg-green-50', 'border-green-300');
            updateTotals();
        } else if (code) {
            errorEl.classList.remove('hidden');
            document.getElementById('promoInput').classList.add('animate-shake');
            setTimeout(() => document.getElementById('promoInput').classList.remove('animate-shake'), 500);
        }
    }

    // Use points
    function usePoints(points, discount) {
        if (state.userPoints >= points) {
            state.pointsUsed = points;
            state.pointsDiscount = discount;
            state.userPoints -= points;

            document.getElementById('userPoints').textContent = state.userPoints.toLocaleString() + ' pt';
            document.getElementById('pointsDiscount').classList.remove('hidden');
            document.getElementById('pointsAmount').textContent = `-${discount} RON`;
            document.getElementById('pointsApplied').classList.remove('hidden');

            document.querySelectorAll('.points-btn').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-50');
            });

            updateTotals();
        }
    }

    // Clear points
    function clearPoints() {
        state.userPoints += state.pointsUsed;
        state.pointsUsed = 0;
        state.pointsDiscount = 0;

        document.getElementById('userPoints').textContent = state.userPoints.toLocaleString() + ' pt';
        document.getElementById('pointsDiscount').classList.add('hidden');
        document.getElementById('pointsApplied').classList.add('hidden');

        document.querySelectorAll('.points-btn').forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('opacity-50');
        });

        updateTotals();
    }

    // Update totals
    function updateTotals() {
        const total = Math.max(0, state.subtotal - state.promoDiscount - state.pointsDiscount);
        const earnPoints = Math.floor(total * 0.1);
        const monthly = Math.ceil(total / 6);

        document.getElementById('totalPrice').textContent = total.toLocaleString() + ' RON';
        document.getElementById('earnPoints').textContent = earnPoints;
        document.getElementById('monthlyPrice').textContent = monthly.toLocaleString();
    }

    // Check if user is logged in
    function isLoggedIn() {
        return localStorage.getItem('tics_user') !== null || localStorage.getItem('tics_token') !== null;
    }

    // Get city from cart items for nearby suggestions
    function getCartCity() {
        if (state.items.length === 0) return 'bucuresti';
        // Extract city from first cart item venue (format: "Venue Name, City")
        const venue = state.items[0].venue || '';
        const parts = venue.split(',');
        if (parts.length > 1) {
            return parts[parts.length - 1].trim().toLowerCase()
                .replace('bucurești', 'bucuresti')
                .replace(/\s+/g, '-');
        }
        return 'bucuresti';
    }

    // Load suggestions based on login status
    async function loadSuggestions() {
        const container = document.getElementById('suggestionsList');
        const aiHeader = document.getElementById('aiSuggestionsHeader');
        const genericHeader = document.getElementById('genericSuggestionsHeader');
        const loggedIn = isLoggedIn();

        // Toggle headers based on login status
        if (loggedIn) {
            aiHeader.classList.remove('hidden');
            aiHeader.classList.add('flex');
            genericHeader.classList.add('hidden');
        } else {
            genericHeader.classList.remove('hidden');
            genericHeader.classList.add('flex');
            aiHeader.classList.add('hidden');
        }

        try {
            // Build query params based on login status
            const params = { per_page: 3 };
            if (!loggedIn) {
                // For non-logged users, get events near the cart items' city
                params.city = getCartCity();
            }

            const response = await TicsAPI.getEvents(params);
            if (response.success && response.data) {
                const events = response.data.slice(0, 3);
                container.innerHTML = events.map(event => `
                    <a href="/bilete/${event.slug}" class="suggestion-card bg-white rounded-xl border border-gray-200 overflow-hidden">
                        <div class="aspect-video relative">
                            <img src="${event.image || 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=200&fit=crop'}" class="w-full h-full object-cover">
                            ${loggedIn ? `<span class="absolute top-2 left-2 px-2 py-1 bg-green-500 text-white text-xs font-bold rounded">${event.ai_match || 90}% Match</span>` : ''}
                        </div>
                        <div class="p-4">
                            <p class="text-xs text-gray-500 mb-1">${TicsUtils?.formatDate ? TicsUtils.formatDate(event.starts_at) : event.starts_at}</p>
                            <h4 class="font-semibold text-gray-900">${event.name}</h4>
                            <p class="text-sm text-gray-500 mb-2">${event.venue?.name || ''}</p>
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-gray-900">${event.price_from || 0} RON</span>
                                <button class="px-3 py-1.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors" onclick="event.preventDefault(); addToCart(${JSON.stringify(event).replace(/"/g, '&quot;')})">+ Adaugă</button>
                            </div>
                        </div>
                    </a>
                `).join('');
            }
        } catch (error) {
            console.error('Error loading suggestions:', error);
        }
    }

    // Add to cart
    function addToCart(event) {
        const existing = state.items.find(i => i.id === event.id);
        if (existing) {
            existing.quantity = Math.min(10, existing.quantity + 1);
        } else {
            state.items.push({
                id: event.id,
                name: event.name,
                venue: event.venue?.name + ', ' + event.venue?.city,
                date: TicsUtils?.formatDate ? TicsUtils.formatDate(event.starts_at) : event.starts_at,
                time: TicsUtils?.formatTime ? TicsUtils.formatTime(event.starts_at) : '20:00',
                image: event.image || 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=200&h=200&fit=crop',
                ticketType: 'General Admission',
                price: event.price_from || 100,
                quantity: 1,
                slug: event.slug
            });
        }
        saveCart();
        renderCartItems();
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', initCart);
</script>

</body>
</html>
