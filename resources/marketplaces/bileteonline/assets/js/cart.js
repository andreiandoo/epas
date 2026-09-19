/**
 * bilete.online - Shopping Cart Manager
 * Handles cart operations with localStorage persistence
 */

const BileteOnlineCart = {
    // Storage key
    STORAGE_KEY: 'bileteonline_cart',
    PROMO_KEY: 'bileteonline_cart_promo',
    RESERVATION_KEY: 'bileteonline_cart_reservation',

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
        window.dispatchEvent(new CustomEvent('bileteonline:cart:update', {
            detail: { cart, itemCount: this.getItemCount() }
        }));
    },

    /**
     * Add item to cart
     */
    addItem(eventId, eventData, ticketTypeId, ticketTypeData, quantity = 1, meta = null) {
        // Support alternative signature: addItem(eventData, ticketTypeData, quantity, meta)
        if (typeof eventId === 'object' && eventId !== null) {
            meta = ticketTypeId; // 4th arg
            quantity = ticketTypeData || 1; // 3rd arg
            ticketTypeData = eventData; // 2nd arg
            eventData = eventId; // 1st arg
            ticketTypeId = ticketTypeData.id;
            eventId = eventData.id;
        }
        const cart = this.getCart();
        const perfId = eventData.performance_id || 0;
        const visitDate = meta?.visit_date || eventData.visit_date || '';
        const itemKey = `${eventId}_${ticketTypeId}${perfId ? '_' + perfId : ''}${visitDate ? '_' + visitDate : ''}`;

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
                    venue: eventData.venue,
                    city: eventData.venue?.city,
                    taxes: eventData.taxes || [],
                    target_price: eventData.target_price || null,
                    commission_rate: eventData.commission_rate || 5,
                    commission_mode: eventData.commission_mode || 'included',
                    preview_token: eventData.preview_token || null,
                    performance_id: eventData.performance_id || null,
                    performance_date: eventData.performance_date || null,
                    performance_time: eventData.performance_time || null,
                    performance_label: eventData.performance_label || null
                },
                ticketTypeId,
                ticketType: {
                    id: ticketTypeData.id,
                    name: ticketTypeData.name,
                    price: ticketTypeData.price,
                    originalPrice: ticketTypeData.original_price,
                    description: ticketTypeData.description,
                    min_per_order: ticketTypeData.min_per_order || 1,
                    max_per_order: ticketTypeData.max_per_order || 10,
                    commission: ticketTypeData.commission || null, // Per-ticket commission settings
                    is_refundable: ticketTypeData.is_refundable || false,
                    is_parking: ticketTypeData.is_parking || false,
                    requires_vehicle_info: ticketTypeData.requires_vehicle_info || false
                },
                quantity,
                meta: meta || null,
                addedAt: new Date().toISOString()
            });
        }

        this.saveCart(cart);

        // Start/reset reservation timer when adding items
        this.startReservationTimer();

        this.showNotification(`${ticketTypeData.name} adăugat în coș!`);

        // CAPI AddToCart (Layer B bridge â€” backend forwards to Meta Graph API)
        try {
            if (window.EPASTracking && typeof EPASTracking.trackAddToCart === 'function') {
                EPASTracking.trackAddToCart(
                    eventId,
                    ticketTypeId,
                    quantity,
                    ticketTypeData.price || 0,
                    'RON',
                    {
                        marketplace_event_id: eventId,
                        content_name: ticketTypeData.name || null,
                    }
                );
            }
        } catch (e) {
            // Tracking must never break cart logic
        }

        return cart;
    },

    /**
     * Add an Activity item to the cart.
     *
     * Activity items live alongside event items in the same cart array but
     * carry `type: 'activity'` so cart-page.js / checkout-page.js / the
     * backend checkout flow can branch on them. The shape matches what
     * CheckoutController::processActivityCheckout expects.
     *
     * @param {Object} activityData {id, slug, title, image, venue, city, organizer_id}
     * @param {Object} variantData  {id, name, price_cents, capacity_share}
     * @param {Object} slotData     {date, start_time, end_time}
     * @param {Number} participantsCount
     */
    addActivityItem(activityData, variantData, slotData, participantsCount = 1) {
        const cart = this.getCart();

        const date  = slotData.date || slotData.booking_date;
        const start = slotData.start_time || slotData.slot_start_time;
        const end   = slotData.end_time   || slotData.slot_end_time   || null;

        if (!activityData?.id || !variantData?.id || !date || !start || participantsCount < 1) {
            // Return null (not the existing cart) so callers can detect that
            // nothing was actually added. Previously this returned the
            // unchanged cart and submitBooking still incremented `pushed`,
            // redirecting the user to /cos even when no item was saved.
            console.warn('[BileteOnlineCart.addActivityItem] guard rejected; nothing saved', {
                hasActivityId:     !!activityData?.id,
                hasVariantId:      !!variantData?.id,
                hasDate:           !!date,
                hasStart:          !!start,
                participantsCount,
                slotData,
            });
            return null;
        }

        const itemKey = `activity_${activityData.id}_${variantData.id}_${date}_${start}`;
        const priceRon = typeof variantData.price === 'number'
            ? variantData.price
            : (variantData.price_cents ? variantData.price_cents / 100 : 0);

        const existingIndex = cart.items.findIndex(it => it.key === itemKey);
        if (existingIndex >= 0) {
            // Same activity + slot + variant already in cart → bump count
            cart.items[existingIndex].participants_count =
                (cart.items[existingIndex].participants_count || 0) + participantsCount;
            cart.items[existingIndex].quantity = cart.items[existingIndex].participants_count;
        } else {
            cart.items.push({
                key: itemKey,
                type: 'activity',
                activity_id: activityData.id,
                variant_id: variantData.id,
                booking_date: date,
                slot_start_time: start,
                slot_end_time: end,
                participants_count: participantsCount,
                // legacy alias so renderers / API parsers that read .quantity work
                quantity: participantsCount,
                price: priceRon,
                activity: {
                    id: activityData.id,
                    slug: activityData.slug || null,
                    title: activityData.title || '',
                    image: activityData.image || activityData.cover_image_url || null,
                    venue: activityData.venue || null,
                    city: activityData.city || null,
                    organizer_id: activityData.organizer_id || null,
                    duration_minutes: activityData.duration_minutes || null,
                    // Effective organizer commission — set by the activity
                    // detail page from ActivitiesController::show. Without it,
                    // calculateItemCommission falls back to a 5% built-in
                    // default which is almost always wrong.
                    commission_rate: typeof activityData.commission_rate === 'number' ? activityData.commission_rate : null,
                    commission_mode: activityData.commission_mode || null,
                },
                variant: {
                    id: variantData.id,
                    name: variantData.name || 'Bilet',
                    price: priceRon,
                    capacity_share: variantData.capacity_share || 1,
                },
                addedAt: new Date().toISOString(),
            });
        }

        this.saveCart(cart);
        this.startReservationTimer();
        this.showNotification(`${variantData.name || 'Bilet'} adăugat în coș!`);
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
                // Remove item if quantity is 0 or less; release seats first
                const [removed] = cart.items.splice(index, 1);
                this._releaseItemSeats(removed);
            } else {
                cart.items[index].quantity = quantity;
                // Activity lines count participants; quantity is only the legacy alias of participants_count
                if (cart.items[index].type === 'activity') cart.items[index].participants_count = quantity;
            }
            this.saveCart(cart);
        }

        return cart;
    },

    /**
     * Release held seats for a cart item via API (best-effort).
     * Silently skips items without seats and never throws â€” local cart
     * mutation must proceed even if the network call fails.
     */
    _releaseItemSeats(item) {
        if (!item || !item.seat_uids || item.seat_uids.length === 0 || !item.event_seating_id) {
            return Promise.resolve();
        }
        if (typeof BileteOnlineAPI === 'undefined' || !BileteOnlineAPI.delete) {
            return Promise.resolve();
        }
        return BileteOnlineAPI.delete('/cart/seats', {
            event_seating_id: item.event_seating_id,
            seat_uids: item.seat_uids
        }).catch(function(error) {
            console.warn('[BileteOnlineCart] Failed to release seats (cleanup job will handle):', error);
        });
    },

    /**
     * Remove item from cart. Releases any held seats via API before mutating storage.
     */
    removeItem(itemKey) {
        const cart = this.getCart();
        const index = cart.items.findIndex(item => item.key === itemKey);

        if (index >= 0) {
            const removed = cart.items.splice(index, 1)[0];
            this._releaseItemSeats(removed);
            this.saveCart(cart);
            this.showNotification(`${this._lineName(removed)} eliminat din coș`);
        }

        return cart;
    },

    /**
     * Clear entire cart. Releases all held seats unless skipRelease is set
     * (used after a successful checkout where seats are already sold).
     */
    clearCart(options = {}) {
        if (!options.skipRelease) {
            const current = this.getCart();
            (current.items || []).forEach(item => this._releaseItemSeats(item));
        }

        const cart = { items: [], updatedAt: new Date().toISOString() };
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(cart));
        localStorage.removeItem(this.PROMO_KEY);
        localStorage.removeItem(this.RESERVATION_KEY);
        // Also clear cart_end_time used by cart.php
        localStorage.removeItem('cart_end_time');

        window.dispatchEvent(new CustomEvent('bileteonline:cart:clear'));
        window.dispatchEvent(new CustomEvent('bileteonline:cart:update', {
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
            return total + (this._unitPrice(item) * this._lineQuantity(item));
        }, 0);
    },

    // Activity items carry variant + participants_count instead of ticketType + quantity. These read either shape;
    // the ticketType-only versions threw on activities (e.g. every promo code failed with a TypeError).
    _unitPrice(item) {
        if (item.type === 'activity') {
            return (typeof item.variant?.price === 'number' ? item.variant.price : item.price) || 0;
        }
        return item.ticketType?.price || item.price || 0;
    },

    _originalPrice(item) {
        return (item.type === 'activity' ? item.variant?.originalPrice : item.ticketType?.originalPrice) || item.original_price || 0;
    },

    _lineQuantity(item) {
        return (item.type === 'activity' ? item.participants_count : 0) || item.quantity || 0;
    },

    _lineName(item) {
        return (item.type === 'activity' ? item.variant?.name : item.ticketType?.name) || item.ticket_type_name || 'Bilet';
    },

    /**
     * Calculate total savings (from discounted tickets)
     */
    getSavings() {
        const cart = this.getCart();
        return cart.items.reduce((total, item) => {
            const price = this._unitPrice(item), original = this._originalPrice(item);
            if (original && original > price) {
                return total + ((original - price) * this._lineQuantity(item));
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
            const price = this._unitPrice(item), original = this._originalPrice(item);
            if (original && original > price) {
                totalSavings += (original - price) * this._lineQuantity(item);
                ticketNames.push(this._lineName(item));
            }
        });

        return {
            amount: totalSavings,
            ticketNames: [...new Set(ticketNames)] // Remove duplicates
        };
    },

    /**
     * Get ALL taxes from cart items (stored when adding to cart from event page)
     * Returns taxes from first cart item or empty array if not available
     */
    getTaxes() {
        const items = this.getItems();
        if (items.length > 0 && items[0].event?.taxes?.length > 0) {
            // Return ALL active taxes (both included in price and added on top)
            return items[0].event.taxes.filter(t => t.is_active !== false);
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
     * Calculate commission for a single ticket type
     * @param {Object} item - Cart item with ticketType and event data
     * @returns {Object} Commission details: { amount, rate, fixed, mode, type }
     */
    calculateItemCommission(item) {
        // Activity bookings have a different shape (item.variant.price +
        // item.activity instead of item.ticketType + item.event). Without
        // this branch the legacy `item.ticketType.price` access throws
        // TypeError and the entire summary updateSummary() forEach blows
        // up — that's why the right-hand summary stayed at 0 / 0 lei.
        const isActivity = item.type === 'activity';
        if (isActivity) {
            const basePrice = (typeof item.variant?.price === 'number' ? item.variant.price : item.price) || 0;
            const c = item.variant?.commission || item.activity?.commission || null;
            // Read organizer commission as stored on the cart item — set
            // by activitate.php from the API. Only fall back to a 5%
            // marketplace-default percentage when both are nullish (i.e.
            // legacy items added before this field existed).
            const fallbackRate = (typeof item.activity?.commission_rate === 'number')
                ? item.activity.commission_rate
                : 5;
            const fallbackMode = item.activity?.commission_mode || 'included';
            if (c && c.type) {
                let amount = 0;
                switch (c.type) {
                    case 'percentage': amount = basePrice * ((c.rate || 0) / 100); break;
                    case 'fixed':      amount = c.fixed || 0; break;
                    case 'both':       amount = (basePrice * ((c.rate || 0) / 100)) + (c.fixed || 0); break;
                }
                return {
                    amount: amount,
                    rate:   c.rate || 0,
                    fixed:  c.fixed || 0,
                    mode:   c.mode || fallbackMode,
                    type:   c.type,
                };
            }
            return {
                amount: basePrice * (fallbackRate / 100),
                rate:   fallbackRate,
                fixed:  0,
                mode:   fallbackMode,
                type:   'percentage',
            };
        }

        // === Legacy event-ticket shape ===
        const basePrice = item.ticketType?.price || item.price || 0;
        const commission = item.ticketType?.commission;

        // If ticket has per-ticket commission settings
        if (commission && commission.type) {
            let amount = 0;
            switch (commission.type) {
                case 'percentage':
                    amount = basePrice * ((commission.rate || 0) / 100);
                    break;
                case 'fixed':
                    amount = commission.fixed || 0;
                    break;
                case 'both':
                    amount = (basePrice * ((commission.rate || 0) / 100)) + (commission.fixed || 0);
                    break;
            }
            return {
                amount: amount,
                rate: commission.rate || 0,
                fixed: commission.fixed || 0,
                mode: commission.mode || 'included',
                type: commission.type
            };
        }

        // Fall back to event-level commission
        const eventRate = item.event?.commission_rate || 5;
        const eventMode = item.event?.commission_mode || 'included';
        return {
            amount: basePrice * (eventRate / 100),
            rate: eventRate,
            fixed: 0,
            mode: eventMode,
            type: 'percentage'
        };
    },

    /**
     * Calculate total commission for all items in cart
     * @returns {number} Total commission amount
     */
    getTotalCommission() {
        const cart = this.getCart();
        return cart.items.reduce((total, item) => {
            const commission = this.calculateItemCommission(item);
            return total + (commission.amount * this._lineQuantity(item));
        }, 0);
    },

    /**
     * Get commission breakdown by item for reporting
     * @returns {Array} Array of { item, commission, quantity, total }
     */
    getCommissionBreakdown() {
        const cart = this.getCart();
        return cart.items.map(item => {
            const commission = this.calculateItemCommission(item);
            return {
                ticketName: this._lineName(item),
                eventTitle: item.event?.title || item.activity?.title || '',
                basePrice: this._unitPrice(item),
                commission: commission,
                quantity: this._lineQuantity(item),
                totalCommission: commission.amount * this._lineQuantity(item)
            };
        });
    },

    /**
     * Calculate points to be earned
     */
    getPointsEarned() {
        return Math.floor(this.getSubtotal() * BILETEONLINE_CONFIG.POINTS_PER_CURRENCY);
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

        // Promo codes are validated against an event (the API requires event_id), so only event tickets count.
        // For a cart of event tickets only this is exactly what was sent before.
        const eventItems = cart.items.filter(item => item.type !== 'activity');
        if (eventItems.length === 0) {
            return { success: false, message: 'Codurile promoționale se aplică doar biletelor la evenimente, nu și rezervărilor de activități.' };
        }

        try {
            // First event ID for validation (simplified)
            const eventId = eventItems[0].eventId;
            const subtotal = eventItems.reduce((total, item) => total + (this._unitPrice(item) * this._lineQuantity(item)), 0);
            const ticketCount = eventItems.reduce((total, item) => total + (item.quantity || 0), 0);

            // Build cart items with ticket_type_id for ticket-type-specific discounts
            const items = eventItems.map(item => ({
                event_id: item.eventId,
                ticket_type_id: item.ticketTypeId,
                quantity: item.quantity,
                unit_price: item.ticketType?.price || 0,
                total: (item.ticketType?.price || 0) * item.quantity
            }));

            const response = await BileteOnlineAPI.validatePromoCode(
                code,
                eventId,
                subtotal,
                ticketCount,
                BileteOnlineAuth.getCustomerData()?.email,
                items
            );

            if (response.success) {
                // Use the backend-calculated discount amount (respects ticket type restrictions)
                const discountData = response.data.discount || {};
                const promoCode = response.data.promo_code || {};
                const promoData = {
                    code: code,
                    type: response.data.discount_type || promoCode.type,
                    value: response.data.discount_value || promoCode.value,
                    discountAmount: discountData.amount || response.data.discount_amount || 0,
                    appliedToLabel: promoCode.applied_to_label || null,
                    appliedAt: new Date().toISOString()
                };

                localStorage.setItem(this.PROMO_KEY, JSON.stringify(promoData));

                window.dispatchEvent(new CustomEvent('bileteonline:cart:promo', {
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

        window.dispatchEvent(new CustomEvent('bileteonline:cart:promo', {
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

        // Use the backend-calculated discount amount (respects ticket type restrictions)
        if (promo.discountAmount !== undefined && promo.discountAmount !== null) {
            return parseFloat(promo.discountAmount) || 0;
        }

        // Fallback: recalculate (only for old stored promos without discountAmount)
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
            pointsEarned: this.getPointsEarned(),
            totalCommission: this.getTotalCommission(),
            commissionBreakdown: this.getCommissionBreakdown()
        };
    },

    // ==================== RESERVATION TIMER ====================

    /**
     * Start reservation timer
     */
    startReservationTimer() {
        const expiresAt = new Date(Date.now() + BILETEONLINE_CONFIG.CART_RESERVATION_MINUTES * 60 * 1000);
        localStorage.setItem(this.RESERVATION_KEY, expiresAt.toISOString());

        // Also set cart_end_time for global timer bar compatibility
        localStorage.setItem('cart_end_time', expiresAt.getTime().toString());

        window.dispatchEvent(new CustomEvent('bileteonline:cart:reservation', {
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
        window.dispatchEvent(new CustomEvent('bileteonline:notification', {
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

    // Per-marketplace payment-fee config. Loaded lazily on first call to
    // getPaymentFeeConfig() so non-checkout pages don't pay for the fetch.
    // {pass_to_customer, providers:{stripe:{percent_rate, fixed_cents}}, default_provider}.
    _paymentFeeConfig: null,
    _paymentFeeFetched: false,

    /**
     * Fetch payment processing fee config from /marketplace-config/checkout/features.
     * Cached on the singleton — only one network call per page.
     * Returns null when the marketplace hasn't opted into F1 fees, so the
     * cart + checkout summary code can gate on truthiness.
     */
    async loadPaymentFeeConfig() {
        if (this._featuresPromise) {
            await this._featuresPromise;
            return this._paymentFeeConfig;
        }
        this._paymentFeeFetched = true;
        this._featuresPromise = (async () => {
            try {
                // The proxy maps '/checkout/features' → action 'checkout.features'
                // which forwards to the Laravel /marketplace-config/checkout/features
                // endpoint. We use the short URL here so api.js picks the right
                // action from getProxyAction(). Pages without api.js (the activity
                // page) ask the proxy directly.
                let r;
                if (typeof BileteOnlineAPI !== 'undefined') {
                    r = await BileteOnlineAPI.get('/checkout/features');
                } else {
                    const res = await fetch((window.BILETEONLINE?.apiUrl || '/api/proxy.php') + '?action=checkout.features', { headers: { Accept: 'application/json' } });
                    r = res.ok ? await res.json() : null;
                }
                const fees = r?.data?.payment_fees || null;
                if (fees && fees.providers && Object.keys(fees.providers).length > 0) {
                    this._paymentFeeConfig = fees;
                }
                // Points rules (null when the marketplace has no automatic rewards)
                const loyalty = r?.data?.loyalty || null;
                this._loyaltyConfig = loyalty && loyalty.enabled ? loyalty : null;
            } catch (e) {
                // Silent — fee preview is a UX enhancement, not a blocker.
            }
        })();
        await this._featuresPromise;
        return this._paymentFeeConfig;
    },

    // ---- loyalty points (rules from /checkout/features; mirrors core's MarketplaceLoyaltyService) ----
    _loyaltyConfig: null,

    async loadLoyaltyConfig() {
        await this.loadPaymentFeeConfig();
        return this._loyaltyConfig;
    },

    getLoyaltyConfig() {
        return this._loyaltyConfig;
    },

    /** Points earned on an amount (lei): earn_percentage% of it returned as points. 0 when the programme is off. */
    estimatePoints(amountLei) {
        const c = this._loyaltyConfig;
        const value = c ? (Number(c.point_value) || 0.01) : 0;
        if (!c || !value || !(amountLei > 0) || amountLei < (Number(c.min_order_for_earning) || 0)) return 0;
        return Math.floor(Math.round(amountLei * (Number(c.earn_percentage) || 0) / 100 / value * 1e6) / 1e6);
    },

    /** Most points an order may use: max % of the ticket value, the per-order cap, the balance; 0 under the minimum. */
    maxRedeemablePoints(ticketValue, balance) {
        const c = this._loyaltyConfig;
        const value = c ? (Number(c.point_value) || 0.01) : 0;
        balance = Math.floor(Number(balance) || 0);
        if (!c || !value || !(ticketValue > 0) || balance <= 0 || balance < (Number(c.min_redeem_points) || 0)) return 0;
        const pct = Number(c.max_redeem_percentage) || 0;
        let max = pct > 0 ? Math.floor(Math.round(ticketValue * pct / 100 / value * 1e6) / 1e6) : 0;
        const cap = Math.floor(Number(c.max_redeem_points_per_order) || 0);
        if (cap > 0) max = Math.min(max, cap);
        return Math.max(0, Math.min(max, balance));
    },

    pointsValue(points) {
        const c = this._loyaltyConfig;
        return Math.round((Math.floor(points) || 0) * (c ? (Number(c.point_value) || 0.01) : 0.01) * 100) / 100;
    },

    getPaymentFeeConfig() {
        return this._paymentFeeConfig;
    },

    /**
     * Compute the processing fee for a given subtotal (RON, not cents).
     * Mirror of server-side ProcessingFeeCalculator::compute() so the
     * preview matches what the order will actually charge.
     * Returns {amount, percent_rate, fixed, provider, label, pass_to_customer}.
     * amount is always in RON (lei); 0 when not configured or pass_to_customer=false.
     */
    computeProcessingFee(subtotalRon, providerKey) {
        const cfg = this._paymentFeeConfig;
        if (! cfg || ! cfg.pass_to_customer) {
            return { amount: 0, percent_rate: 0, fixed: 0, provider: null, label: '', pass_to_customer: false };
        }
        const key = providerKey || cfg.default_provider || Object.keys(cfg.providers)[0];
        const p = cfg.providers[key];
        if (! p) {
            return { amount: 0, percent_rate: 0, fixed: 0, provider: null, label: '', pass_to_customer: true };
        }
        const subtotalCents = Math.round(subtotalRon * 100);
        // Match server: floor(percent component) + fixed component.
        const percentFeeCents = Math.floor(subtotalCents * (p.percent_rate || 0) / 100);
        const feeCents = percentFeeCents + (p.fixed_cents || 0);
        return {
            amount: feeCents / 100,
            percent_rate: p.percent_rate || 0,
            fixed: (p.fixed_cents || 0) / 100,
            provider: key,
            label: p.label || 'Procesare card',
            pass_to_customer: true,
        };
    },

    /**
     * Initialize cart (call on page load)
     */
    init() {
        // Update cart badge
        this.updateCartBadge();

        // Pre-warm the payment fee config so cart/checkout summary lines
        // appear on first paint instead of after a delayed re-render.
        try { this.loadPaymentFeeConfig(); } catch (e) {}

        // Listen for storage changes (multi-tab sync)
        window.addEventListener('storage', (e) => {
            if (e.key === this.STORAGE_KEY) {
                this.updateCartBadge();
                window.dispatchEvent(new CustomEvent('bileteonline:cart:sync', {
                    detail: { cart: this.getCart() }
                }));
            }
        });

        // Listen for cart updates
        window.addEventListener('bileteonline:cart:update', () => {
            this.updateCartBadge();
        });

        // Check reservation expiry on load and clear if expired
        if (this.getItemCount() > 0 && this.isReservationExpired()) {
            this.handleReservationExpired();
        }

        // Start periodic check for reservation expiry (every 5 seconds)
        this.startExpiryCheck();
    },

    /**
     * Handle cart reservation expiration
     */
    handleReservationExpired() {
        const hadItems = this.getItemCount() > 0;

        // Clear the cart
        this.clearCart();

        // Dispatch specific expiration event for UI updates
        window.dispatchEvent(new CustomEvent('bileteonline:cart:expired', {
            detail: { hadItems }
        }));

        // Show notification to user
        if (hadItems) {
            this.showNotification('Timpul de rezervare a expirat. Coșul a fost golit.', 'warning');
        }

        console.log('Cart reservation expired - cart cleared');
    },

    /**
     * Start periodic check for reservation expiry
     */
    startExpiryCheck() {
        // Clear any existing interval
        if (this._expiryCheckInterval) {
            clearInterval(this._expiryCheckInterval);
        }

        // Check every 5 seconds
        this._expiryCheckInterval = setInterval(() => {
            if (this.getItemCount() > 0 && this.isReservationExpired()) {
                this.handleReservationExpired();
            }
        }, 5000);
    },

    /**
     * Stop periodic expiry check
     */
    stopExpiryCheck() {
        if (this._expiryCheckInterval) {
            clearInterval(this._expiryCheckInterval);
            this._expiryCheckInterval = null;
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
        window.dispatchEvent(new CustomEvent('bileteonline:cart:update', {
            detail: { cart, itemCount: items.reduce((sum, item) => sum + (item.quantity || 1), 0) }
        }));
    },

    /**
     * Alias for clearCart (CartPage compatibility)
     */
    clear(options = {}) {
        return this.clearCart(options);
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    BileteOnlineCart.init();
});
