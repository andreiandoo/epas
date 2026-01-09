/**
 * Ambilet.ro - Shopping Cart Manager
 * Handles cart operations with localStorage persistence
 */

const AmbiletCart = {
    // Storage key
    STORAGE_KEY: 'ambilet_cart',
    PROMO_KEY: 'ambilet_cart_promo',
    RESERVATION_KEY: 'ambilet_cart_reservation',

    /**
     * Get cart from localStorage
     */
    getCart() {
        const cart = localStorage.getItem(this.STORAGE_KEY);
        return cart ? JSON.parse(cart) : { items: [], updatedAt: null };
    },

    /**
     * Save cart to localStorage
     */
    saveCart(cart) {
        cart.updatedAt = new Date().toISOString();
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));

        // Dispatch cart update event
        window.dispatchEvent(new CustomEvent('ambilet:cart:update', {
            detail: { cart, itemCount: this.getItemCount() }
        }));
    },

    /**
     * Add item to cart
     */
    addItem(eventId, eventData, ticketTypeId, ticketTypeData, quantity = 1) {
        const cart = this.getCart();
        const itemKey = `${eventId}_${ticketTypeId}`;

        // Find existing item
        const existingIndex = cart.items.findIndex(item => item.key === itemKey);

        if (existingIndex >= 0) {
            // Update quantity
            cart.items[existingIndex].quantity += quantity;
        } else {
            // Add new item
            cart.items.push({
                key: itemKey,
                eventId,
                event: {
                    id: eventData.id,
                    title: eventData.title,
                    slug: eventData.slug,
                    date: eventData.start_date,
                    time: eventData.start_time,
                    image: eventData.image || eventData.featured_image,
                    venue: eventData.venue?.name,
                    city: eventData.venue?.city
                },
                ticketTypeId,
                ticketType: {
                    id: ticketTypeData.id,
                    name: ticketTypeData.name,
                    price: ticketTypeData.price,
                    originalPrice: ticketTypeData.original_price,
                    description: ticketTypeData.description
                },
                quantity,
                addedAt: new Date().toISOString()
            });
        }

        this.saveCart(cart);
        this.showNotification(`${ticketTypeData.name} adăugat în coș!`);

        return cart;
    },

    /**
     * Update item quantity
     */
    updateQuantity(itemKey, quantity) {
        const cart = this.getCart();
        const index = cart.items.findIndex(item => item.key === itemKey);

        if (index >= 0) {
            if (quantity <= 0) {
                // Remove item if quantity is 0 or less
                cart.items.splice(index, 1);
            } else {
                cart.items[index].quantity = quantity;
            }
            this.saveCart(cart);
        }

        return cart;
    },

    /**
     * Remove item from cart
     */
    removeItem(itemKey) {
        const cart = this.getCart();
        const index = cart.items.findIndex(item => item.key === itemKey);

        if (index >= 0) {
            const removed = cart.items.splice(index, 1)[0];
            this.saveCart(cart);
            this.showNotification(`${removed.ticketType.name} eliminat din coș`);
        }

        return cart;
    },

    /**
     * Clear entire cart
     */
    clearCart() {
        const cart = { items: [], updatedAt: new Date().toISOString() };
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
        localStorage.removeItem(this.PROMO_KEY);
        localStorage.removeItem(this.RESERVATION_KEY);

        window.dispatchEvent(new CustomEvent('ambilet:cart:clear'));
        window.dispatchEvent(new CustomEvent('ambilet:cart:update', {
            detail: { cart, itemCount: 0 }
        }));

        return cart;
    },

    /**
     * Get total number of items in cart
     */
    getItemCount() {
        const cart = this.getCart();
        return cart.items.reduce((total, item) => total + item.quantity, 0);
    },

    /**
     * Calculate cart subtotal
     */
    getSubtotal() {
        const cart = this.getCart();
        return cart.items.reduce((total, item) => {
            return total + (item.ticketType.price * item.quantity);
        }, 0);
    },

    /**
     * Calculate total savings (from discounted tickets)
     */
    getSavings() {
        const cart = this.getCart();
        return cart.items.reduce((total, item) => {
            if (item.ticketType.originalPrice && item.ticketType.originalPrice > item.ticketType.price) {
                return total + ((item.ticketType.originalPrice - item.ticketType.price) * item.quantity);
            }
            return total;
        }, 0);
    },

    /**
     * Get savings with ticket names for display message
     * Returns { amount: number, ticketNames: string[] }
     */
    getSavingsWithTickets() {
        const cart = this.getCart();
        let totalSavings = 0;
        const ticketNames = [];

        cart.items.forEach(item => {
            if (item.ticketType.originalPrice && item.ticketType.originalPrice > item.ticketType.price) {
                totalSavings += (item.ticketType.originalPrice - item.ticketType.price) * item.quantity;
                ticketNames.push(item.ticketType.name);
            }
        });

        return {
            amount: totalSavings,
            ticketNames: [...new Set(ticketNames)] // Remove duplicates
        };
    },

    /**
     * Get configured taxes
     * Returns taxes from config or empty array if not configured
     */
    getTaxes() {
        if (typeof AMBILET_CONFIG !== 'undefined' && AMBILET_CONFIG.TAXES) {
            const taxes = [];
            if (AMBILET_CONFIG.TAXES.RED_CROSS) {
                taxes.push({
                    name: 'Taxa Crucea Roșie',
                    value: AMBILET_CONFIG.TAXES.RED_CROSS * 100, // Convert to percent (0.01 -> 1)
                    value_type: 'percent',
                    is_active: true
                });
            }
            return taxes;
        }
        return [];
    },

    /**
     * Calculate total taxes based on configured taxes
     */
    getTotalTaxes() {
        const subtotal = this.getSubtotal();
        const taxes = this.getTaxes();
        let totalTaxes = 0;

        taxes.forEach(tax => {
            if (!tax.is_active) return;
            if (tax.value_type === 'percent') {
                totalTaxes += subtotal * (tax.value / 100);
            } else if (tax.value_type === 'fixed') {
                totalTaxes += tax.value * this.getItemCount();
            }
        });

        return totalTaxes;
    },

    /**
     * Calculate Red Cross tax (legacy method for backwards compatibility)
     * @deprecated Use getTotalTaxes() instead
     */
    getRedCrossTax() {
        return this.getTotalTaxes();
    },

    /**
     * Calculate points to be earned
     */
    getPointsEarned() {
        return Math.floor(this.getSubtotal() * AMBILET_CONFIG.POINTS_PER_CURRENCY);
    },

    /**
     * Get applied promo code
     */
    getPromoCode() {
        const promo = localStorage.getItem(this.PROMO_KEY);
        return promo ? JSON.parse(promo) : null;
    },

    /**
     * Apply promo code
     */
    async applyPromoCode(code) {
        const cart = this.getCart();
        if (cart.items.length === 0) {
            return { success: false, message: 'Coșul este gol' };
        }

        try {
            // Get first event ID for validation (simplified)
            const eventId = cart.items[0].eventId;
            const subtotal = this.getSubtotal();
            const ticketCount = this.getItemCount();

            const response = await AmbiletAPI.validatePromoCode(
                code,
                eventId,
                subtotal,
                ticketCount,
                AmbiletAuth.getCustomerData()?.email
            );

            if (response.success) {
                const promoData = {
                    code: code,
                    type: response.data.discount_type,
                    value: response.data.discount_value,
                    discountAmount: response.data.discount_amount,
                    appliedAt: new Date().toISOString()
                };

                localStorage.setItem(this.PROMO_KEY, JSON.stringify(promoData));

                window.dispatchEvent(new CustomEvent('ambilet:cart:promo', {
                    detail: { promo: promoData }
                }));

                this.showNotification(`Cod promoțional "${code}" aplicat cu succes!`, 'success');
                return { success: true, promo: promoData };
            }

            return { success: false, message: response.message || 'Cod invalid' };
        } catch (error) {
            return { success: false, message: error.message };
        }
    },

    /**
     * Remove promo code
     */
    removePromoCode() {
        localStorage.removeItem(this.PROMO_KEY);

        window.dispatchEvent(new CustomEvent('ambilet:cart:promo', {
            detail: { promo: null }
        }));

        this.showNotification('Cod promoțional eliminat');
    },

    /**
     * Get discount amount from promo code
     */
    getPromoDiscount() {
        const promo = this.getPromoCode();
        if (!promo) return 0;

        const subtotal = this.getSubtotal();

        if (promo.type === 'percentage') {
            return subtotal * (promo.value / 100);
        }

        return Math.min(promo.value, subtotal);
    },

    /**
     * Calculate grand total
     */
    getTotal() {
        const subtotal = this.getSubtotal();
        const tax = this.getRedCrossTax();
        const discount = this.getPromoDiscount();

        return Math.max(0, subtotal + tax - discount);
    },

    /**
     * Get cart summary
     */
    getSummary() {
        const savingsInfo = this.getSavingsWithTickets();
        return {
            items: this.getCart().items,
            itemCount: this.getItemCount(),
            subtotal: this.getSubtotal(),
            savings: savingsInfo.amount,
            savingsTicketNames: savingsInfo.ticketNames,
            taxes: this.getTaxes(),
            totalTaxes: this.getTotalTaxes(),
            redCrossTax: this.getRedCrossTax(), // Legacy support
            promoCode: this.getPromoCode(),
            promoDiscount: this.getPromoDiscount(),
            total: this.getTotal(),
            pointsEarned: this.getPointsEarned()
        };
    },

    // ==================== RESERVATION TIMER ====================

    /**
     * Start reservation timer
     */
    startReservationTimer() {
        const expiresAt = new Date(Date.now() + AMBILET_CONFIG.CART_RESERVATION_MINUTES * 60 * 1000);
        localStorage.setItem(this.RESERVATION_KEY, expiresAt.toISOString());

        window.dispatchEvent(new CustomEvent('ambilet:cart:reservation', {
            detail: { expiresAt: expiresAt.toISOString() }
        }));

        return expiresAt;
    },

    /**
     * Get reservation expiry time
     */
    getReservationExpiry() {
        const expiry = localStorage.getItem(this.RESERVATION_KEY);
        return expiry ? new Date(expiry) : null;
    },

    /**
     * Check if reservation is expired
     */
    isReservationExpired() {
        const expiry = this.getReservationExpiry();
        if (!expiry) return true;
        return new Date() >= expiry;
    },

    /**
     * Get remaining reservation time in seconds
     */
    getRemainingTime() {
        const expiry = this.getReservationExpiry();
        if (!expiry) return 0;

        const remaining = Math.max(0, expiry.getTime() - Date.now());
        return Math.floor(remaining / 1000);
    },

    /**
     * Format remaining time as MM:SS
     */
    formatRemainingTime() {
        const seconds = this.getRemainingTime();
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    },

    /**
     * Clear reservation
     */
    clearReservation() {
        localStorage.removeItem(this.RESERVATION_KEY);
    },

    // ==================== UI HELPERS ====================

    /**
     * Show notification toast
     */
    showNotification(message, type = 'info') {
        window.dispatchEvent(new CustomEvent('ambilet:notification', {
            detail: { message, type }
        }));
    },

    /**
     * Update cart badge in header
     */
    updateCartBadge() {
        const count = this.getItemCount();
        const badges = document.querySelectorAll('[data-cart-count]');

        badges.forEach(badge => {
            badge.textContent = count;
            badge.style.display = count > 0 ? '' : 'none';
        });
    },

    /**
     * Check if cart has items from multiple events
     */
    hasMultipleEvents() {
        const cart = this.getCart();
        const eventIds = new Set(cart.items.map(item => item.eventId));
        return eventIds.size > 1;
    },

    /**
     * Get items grouped by event
     */
    getItemsByEvent() {
        const cart = this.getCart();
        const grouped = {};

        cart.items.forEach(item => {
            if (!grouped[item.eventId]) {
                grouped[item.eventId] = {
                    event: item.event,
                    items: []
                };
            }
            grouped[item.eventId].items.push(item);
        });

        return grouped;
    },

    /**
     * Initialize cart (call on page load)
     */
    init() {
        // Update cart badge
        this.updateCartBadge();

        // Listen for storage changes (multi-tab sync)
        window.addEventListener('storage', (e) => {
            if (e.key === this.STORAGE_KEY) {
                this.updateCartBadge();
                window.dispatchEvent(new CustomEvent('ambilet:cart:sync', {
                    detail: { cart: this.getCart() }
                }));
            }
        });

        // Listen for cart updates
        window.addEventListener('ambilet:cart:update', () => {
            this.updateCartBadge();
        });

        // Check reservation expiry
        if (this.getItemCount() > 0 && this.isReservationExpired()) {
            // Optionally clear cart or show warning
            console.log('Cart reservation expired');
        }
    },

    /**
     * Get cart items array (alias for CartPage compatibility)
     */
    getItems() {
        return this.getCart().items || [];
    },

    /**
     * Save items array (alias for CartPage compatibility)
     */
    save(items) {
        const cart = { items: items, updatedAt: new Date().toISOString() };
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));

        // Dispatch cart update event
        window.dispatchEvent(new CustomEvent('ambilet:cart:update', {
            detail: { cart, itemCount: items.reduce((sum, item) => sum + (item.quantity || 1), 0) }
        }));
    },

    /**
     * Alias for clearCart (CartPage compatibility)
     */
    clear() {
        return this.clearCart();
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AmbiletCart.init();
});
