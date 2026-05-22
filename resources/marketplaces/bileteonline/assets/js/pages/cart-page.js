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
        const items = BileteOnlineCart.getItems();
        if (items.length > 0 && items[0].event?.taxes?.length > 0) {
            // Show ALL taxes (both included in price and added on top)
            this.taxes = items[0].event.taxes.filter(t => t.is_active !== false);
            return;
        }

        try {
            // Try to load taxes from API
            if (typeof BileteOnlineAPI !== 'undefined') {
                const response = await BileteOnlineAPI.get('/config/taxes');
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
        const items = BileteOnlineCart.getItems();

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
                BileteOnlineCart.clear({ skipRelease: true });
                localStorage.removeItem('cart_end_time');
                this.render();
                if (typeof BileteOnlineNotifications !== 'undefined') {
                    BileteOnlineNotifications.warning('Timpul de rezervare a expirat. Locurile au fost eliberate.');
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
            if (typeof BileteOnlineNotifications !== 'undefined') {
                BileteOnlineNotifications.warning('Mai ai doar 5 minute pentru a finaliza comanda! DupÄƒ expirare, locurile vor fi eliberate.');
            }
        }
    },

    /**
     * Release all held seats via API
     */
    async releaseAllSeats() {
        const items = BileteOnlineCart.getItems();

        for (const item of items) {
            // Check if this item has held seats
            if (item.seat_uids && item.seat_uids.length > 0 && item.event_seating_id) {
                try {
                    console.log('[CartPage] Releasing seats for item:', item.key, item.seat_uids);
                    await BileteOnlineAPI.delete('/cart/seats', {
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
        const items = BileteOnlineCart.getItems();
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
        // Handle both BileteOnlineCart format and legacy format
        const itemKey = item.key || index;
        const eventImage = getStorageUrl(item.event?.image || item.event_image);
        const eventTitle = item.event?.title || item.event_title || 'Eveniment';
        const eventDate = item.event?.performance_date || item.event?.date || item.event_date || '';
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
        const commission = BileteOnlineCart.calculateItemCommission(item);
        const commissionMode = commission.mode || 'included';

        const hasDiscount = originalPrice && originalPrice > price;
        const discountPercent = hasDiscount ? Math.round((1 - price / originalPrice) * 100) : 0;
        const formattedDate = eventDate ? BileteOnlineUtils.formatDate(eventDate, 'medium') : '';

        // Calculate commission - price is always base price
        let commissionAmount = 0;
        if (commissionMode === 'added_on_top') {
            commissionAmount = commission.amount;
        }
        const totalWithCommission = price + commissionAmount;

        // Build tooltip HTML with price breakdown
        let tooltipHtml = '<p class="pb-2 mb-3 text-sm font-semibold border-b border-white/20">Detalii preÈ› bilet ' + ticketTypeName + '</p>' +
            '<div class="space-y-2 text-xs">' +
                '<div class="flex justify-between"><span class="text-white/90">PreÈ› bilet:</span><span>' + price.toFixed(2) + ' lei</span></div>';

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
            tooltipHtml += '<div class="flex justify-between"><span class="text-white/90">' + commissionLabel + ':</span><span>+' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                '<div class="flex justify-between pt-2 mt-2 border-t border-white/20"><span class="font-semibold">Total la platÄƒ:</span><span class="font-semibold">' + totalWithCommission.toFixed(2) + ' lei</span></div>';
        }

        tooltipHtml += '</div>';

        return '<div class="bg-white border-2 cart-item rounded-2xl border-border" data-item-key="' + itemKey + '" data-index="' + index + '">' +
            '<div class="flex gap-4 p-3">' +
                '<div class="w-24 h-24 overflow-hidden rounded-xl shrink-0 mobile:w-12 mobile:h-12">' +
                    '<img src="' + eventImage + '" alt="' + eventTitle + '" class="object-cover w-full h-full" loading="lazy">' +
                '</div>' +
                '<div class="flex-1 min-w-0">' +
                    '<div class="flex items-center justify-between">' +
                        '<div class="flex-1 min-w-0">' +
                            '<h3 class="font-semibold truncate text-secondary">' + eventTitle + '</h3>' +
                            '<p class="text-sm text-muted">' +
                                formattedDate +
                                (venueName ? ' â€¢ ' + venueName : '') +
                            '</p>' +
                        '</div>' +
                        '<button onclick="CartPage.removeItem(' + index + ')" aria-label="È˜terge" class="self-start p-2 transition-colors rounded-lg text-muted hover:text-error hover:bg-red-50">' +
                            '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>' +
                            '</svg>' +
                        '</button>' +
                    '</div>' +
                    '<div class="flex items-center justify-between mt-1 mobile:hidden">' +
                        '<div class="relative inline-block tooltip-trigger">' +
                            '<div class="flex items-center gap-2">' +
                                '<span class="inline-flex items-center px-2 py-1 text-sm font-semibold text-secondary">' + ticketTypeName +
                                    (hasDiscount ? ' <span class="discount-badge text-white text-[10px] font-bold py-0.5 px-1.5 rounded-full ml-1">-' + discountPercent + '%</span>' : '') +
                                '</span>' +
                                '<svg class="w-4 h-4 text-muted cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                            '</div>' +
                            (ticketDescription ? '<p class="text-xs text-muted mt-0.5">' + ticketDescription + '</p>' : '') +
                            (seats.length > 0 ? '<p class="mt-1 mr-4 text-xs text-primary"><svg class="inline w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>' + this.formatSeats(seats) + '</p>' : '') +
                            '<div class="absolute left-0 z-10 p-4 mt-2 text-white shadow-xl tooltip top-full w-72 bg-secondary rounded-xl">' + tooltipHtml + '</div>' +
                        '</div>' +
                        (hasSeats ?
                        '<div class="flex items-center gap-2 ml-auto mr-8">' +
                            '<span class="w-8 font-semibold text-center">' + quantity + '</span>' +
                            '<a href="/bilete/' + eventSlug + '" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-primary border border-primary/30 rounded-lg hover:bg-primary/5 transition-colors flex-none">' +
                                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                                'AdaugÄƒ locuri' +
                            '</a>' +
                        '</div>' :
                        '<div class="flex items-center gap-2 ml-auto mr-8">' +
                            '<button onclick="CartPage.updateQuantity(' + index + ', -1)" aria-label="Scade cantitatea" class="flex items-center justify-center w-8 h-8 border rounded-lg border-border hover:bg-surface">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>' +
                                '</svg>' +
                            '</button>' +
                            '<span class="w-8 font-semibold text-center">' + quantity + '</span>' +
                            '<button onclick="CartPage.updateQuantity(' + index + ', 1)" aria-label="CreÈ™te cantitatea" class="flex items-center justify-center w-8 h-8 border rounded-lg border-border hover:bg-surface">' +
                                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>' +
                                '</svg>' +
                            '</button>' +
                        '</div>') +
                        '<div class="flex-none text-right">' +
                            (hasDiscount ? '<div class="text-sm line-through text-muted">' + BileteOnlineUtils.formatCurrency(originalPrice * quantity) + '</div>' : '') +
                            '<div class="font-bold text-primary">' + BileteOnlineUtils.formatCurrency(price * quantity) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="items-center justify-between hidden px-2 pb-2 mobile:flex">' +
                '<div class="relative inline-block tooltip-trigger">' +
                    '<div class="flex items-center gap-2">' +
                        '<span class="w-8 font-semibold text-center">' + quantity + ' x </span>' +
                        '<span class="inline-flex items-center py-1 pr-2 text-sm font-semibold text-secondary">' + ticketTypeName +
                            (hasDiscount ? ' <span class="discount-badge text-white text-[10px] font-bold py-0.5 px-1.5 rounded-full ml-1">-' + discountPercent + '%</span>' : '') +
                        '</span>' +
                        '<svg class="w-4 h-4 text-muted cursor-help" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' +
                    '</div>' +
                    (ticketDescription ? '<p class="text-xs text-muted mt-0.5">' + ticketDescription + '</p>' : '') +
                    (seats.length > 0 ? '<p class="mt-1 mr-4 text-xs text-primary"><svg class="inline w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>' + this.formatSeats(seats) + '</p>' : '') +
                    '<div class="absolute left-0 z-10 p-4 mt-2 text-white shadow-xl tooltip top-full w-72 bg-secondary rounded-xl">' + tooltipHtml + '</div>' +
                '</div>' +
                '<div class="flex-none text-right">' +
                    '<div class="flex items-center gap-2">' +
                        (hasDiscount ? '<div class="text-sm line-through text-muted">' + BileteOnlineUtils.formatCurrency(originalPrice * quantity) + '</div>' : '') +
                        '<div class="font-bold text-primary">' + BileteOnlineUtils.formatCurrency(price * quantity) + '</div>' +
                    '</div>' +
                    (hasSeats ?
                    '<div class="flex items-center gap-2 ml-auto">' +
                        '<a href="/bilete/' + eventSlug + '" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-primary border border-primary/30 rounded-lg hover:bg-primary/5 transition-colors flex-none">' +
                            '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
                            'AdaugÄƒ locuri' +
                        '</a>' +
                    '</div>' :
                    '<div class="flex items-center gap-2 ml-auto">' +
                        '<button onclick="CartPage.updateQuantity(' + index + ', -1)" aria-label="Scade cantitatea" class="flex items-center justify-center w-8 h-8 border rounded-lg border-border hover:bg-surface">' +
                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>' +
                            '</svg>' +
                        '</button>' +
                        '<span class="w-8 font-semibold text-center">' + quantity + '</span>' +
                        '<button onclick="CartPage.updateQuantity(' + index + ', 1)" aria-label="CreÈ™te cantitatea" class="flex items-center justify-center w-8 h-8 border rounded-lg border-border hover:bg-surface">' +
                            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>' +
                            '</svg>' +
                        '</button>' +
                    '</div>') +
                '</div>' +
            '</div>' +
        '</div>';
    },

    updateQuantity(index, delta) {
        const items = BileteOnlineCart.getItems();
        if (!items[index]) return;

        const item = items[index];

        // For seated cart items quantity is bound to the picked seats â€”
        // bumping qty without picking more seats produced ghost tickets
        // with no seat_uid on the checkout. Force the user back to the
        // event page where they can re-open the seat picker.
        const hasHeldSeats = Array.isArray(item.seat_uids) && item.seat_uids.length > 0;
        if (hasHeldSeats) {
            if (typeof BileteOnlineNotifications !== 'undefined') {
                BileteOnlineNotifications.warning('Pentru bilete cu locuri specifice, schimbÄƒ cantitatea din pagina evenimentului â€” alege/eliminÄƒ locurile pe hartÄƒ.');
            }
            return;
        }

        const currentQty = item.quantity;
        let newQty = currentQty + delta;
        const minQty = item.ticketType?.min_per_order || item.min_per_order || 1;
        const maxQty = item.ticketType?.max_per_order || item.max_per_order || item.max_quantity || 10;

        if (delta > 0 && newQty > maxQty) {
            if (typeof BileteOnlineNotifications !== 'undefined') {
                BileteOnlineNotifications.warning(`PoÈ›i cumpÄƒra maximum ${maxQty} bilete de acest tip`);
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
            BileteOnlineCart.save(items);
            this.render();
        }
    },

    async removeItem(index) {
        const items = BileteOnlineCart.getItems();
        const item = items[index];
        const itemEl = document.querySelector(`[data-index="${index}"]`) || document.querySelector(`.cart-item:nth-child(${index + 1})`);

        // Release seats if this item has them
        if (item && item.seat_uids && item.seat_uids.length > 0 && item.event_seating_id) {
            try {
                console.log('[CartPage] Releasing seats for removed item:', item.seat_uids);
                await BileteOnlineAPI.delete('/cart/seats', {
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
                BileteOnlineCart.save(items);
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
        const items = BileteOnlineCart.getItems();
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
            const eventDate = item.event?.performance_date || item.event?.date || item.event_date || '';
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
            const commission = BileteOnlineCart.calculateItemCommission(item);
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
        document.getElementById('subtotal').textContent = BileteOnlineUtils.formatCurrency(subtotalWithCommission);

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
                    if (group.date) details.push(BileteOnlineUtils.formatDate(group.date, 'short'));
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
                        '<span class="font-medium">' + BileteOnlineUtils.formatCurrency(ticket.lineTotal) + '</span>' +
                    '</div>';
                });
            });

            // Show commission as "Taxe procesare" only if on top and has commission
            if (hasAddedOnTopCommission && totalCommission > 0) {
                breakdownHtml += '<div class="flex justify-between pt-3 mt-3 text-sm border-t border-border">' +
                    '<span class="text-muted">Taxe procesare</span>' +
                    '<span class="font-medium">' + BileteOnlineUtils.formatCurrency(totalCommission) + '</span>' +
                '</div>';
            }

            taxesContainer.innerHTML = breakdownHtml;
        }

        document.getElementById('totalPrice').textContent = BileteOnlineUtils.formatCurrency(total);

        // Discount row
        if (this.discount > 0) {
            document.getElementById('discountRow').classList.remove('hidden');
            document.getElementById('discountAmount').textContent = `-${BileteOnlineUtils.formatCurrency(this.discount)}`;
        } else {
            document.getElementById('discountRow').classList.add('hidden');
        }

        // Savings row with ticket name
        if (savings > 0) {
            document.getElementById('savingsRow').classList.remove('hidden');
            document.getElementById('savings').textContent = BileteOnlineUtils.formatCurrency(savings);

            // Update the savings text to include ticket name(s)
            const savingsTextEl = document.getElementById('savingsText');
            if (savingsTextEl && savingsTickets.length > 0) {
                const ticketNames = [...new Set(savingsTickets)].join(', ');
                savingsTextEl.textContent = `AlegÃ¢nd ${ticketNames} ai economisit:`;
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
            const key = (seat.section || 'SecÈ›iune') + ' - RÃ¢nd ' + (seat.row || '?');
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

    // Markup for the applied-promo message. Includes an "Ã—" button so the
    // user can actually remove the code after applying it.
    renderAppliedPromoMessage(promo, prefix) {
        const label = promo.type === 'percentage'
            ? `-${promo.value}% reducere`
            : `-${BileteOnlineUtils.formatCurrency(promo.value)} reducere`;
        const appliedTo = promo.appliedToLabel
            ? `<br><span class="text-xs text-muted">Aplicat pe: ${promo.appliedToLabel}</span>`
            : '';
        const codeSuffix = prefix.includes(':') ? '' : ` (${promo.code})`;
        return `<span class="inline-flex items-start gap-2">
                    <span class="flex-1">${prefix} ${label}${codeSuffix}${appliedTo}</span>
                    <button type="button" onclick="CartPage.removePromo()" class="flex-shrink-0 inline-flex items-center justify-center w-6 h-6 text-muted hover:text-primary hover:bg-primary/10 rounded-full transition-colors" aria-label="EliminÄƒ codul promoÈ›ional" title="EliminÄƒ codul">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </span>`;
    },

    async applyPromo() {
        const code = document.getElementById('promoCode').value.trim().toUpperCase();
        const messageEl = document.getElementById('promoMessage');

        if (!code) {
            messageEl.textContent = 'Te rugÄƒm sÄƒ introduci un cod promoÈ›ional';
            messageEl.className = 'mt-2 text-sm text-muted';
            messageEl.classList.remove('hidden');
            return;
        }

        // Disable button during validation
        const btn = document.querySelector('#promo-section button');
        if (btn) { btn.disabled = true; btn.textContent = 'Se verificÄƒ...'; }

        const result = await BileteOnlineCart.applyPromoCode(code);

        if (result.success) {
            const promo = result.promo;
            this.discount = BileteOnlineCart.getPromoDiscount();
            this.appliedPromo = code;

            messageEl.innerHTML = this.renderAppliedPromoMessage(promo, 'âœ“ Cod aplicat!');
            messageEl.className = 'mt-2 text-sm text-success';
            messageEl.classList.remove('hidden');

            document.getElementById('promoCode').disabled = true;
            if (btn) { btn.textContent = 'Aplicat'; btn.disabled = true; }

            this.updateSummary();
        } else {
            messageEl.textContent = 'âœ— ' + (result.message || 'Cod invalid sau expirat');
            messageEl.className = 'mt-2 text-sm text-primary';
            messageEl.classList.remove('hidden');
            if (btn) { btn.disabled = false; btn.textContent = 'AplicÄƒ'; }
        }
    },

    // Clear the applied promo code and reset the UI so a new code can be entered.
    removePromo() {
        BileteOnlineCart.removePromoCode();
        this.appliedPromo = null;
        this.discount = 0;

        const messageEl = document.getElementById('promoMessage');
        if (messageEl) {
            messageEl.innerHTML = '';
            messageEl.classList.add('hidden');
        }
        const input = document.getElementById('promoCode');
        if (input) { input.value = ''; input.disabled = false; }
        const btn = document.querySelector('#promo-section button');
        if (btn) { btn.textContent = 'AplicÄƒ'; btn.disabled = false; }

        this.updateSummary();
    },

    loadExistingPromo() {
        const promo = BileteOnlineCart.getPromoCode();
        if (!promo) return;

        // If the cart is empty, any promo left in storage is stale (e.g. the
        // previous order finished but the cart was reopened before storage
        // cleared) â€” wipe it so the new order starts fresh.
        if (BileteOnlineCart.getItemCount() === 0) {
            BileteOnlineCart.removePromoCode();
            return;
        }

        this.discount = BileteOnlineCart.getPromoDiscount();
        this.appliedPromo = promo.code;

        const messageEl = document.getElementById('promoMessage');
        if (messageEl) {
            messageEl.innerHTML = this.renderAppliedPromoMessage(promo, `âœ“ Cod aplicat: ${promo.code}`);
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
window.addEventListener('bileteonline:cart:expired', () => {
    console.log('[CartPage] Cart expired event received');
    if (CartPage.timerInterval) {
        clearInterval(CartPage.timerInterval);
    }
    localStorage.removeItem('cart_end_time');
    CartPage.render();
});

// Initialize featured carousel
document.addEventListener('DOMContentLoaded', () => FeaturedCarousel.init());
