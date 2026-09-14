/**
 * bilete.online — cart page (/cos).
 *
 * Renders the cart kept by assets/js/cart.js (BileteOnlineCart) into the containers of cart.php and keeps the
 * summary, promo code and reservation timer in sync. Markup uses the v2 classes styled in assets/v2/css/cart.css.
 */
const CartPage = {
    timerInterval: null,
    endTime: null,
    appliedPromo: null,
    discount: 0,
    taxes: [], // Dynamic taxes from API/config

    async init() {
        // The summary never reads this.taxes, so the lookup (an API call for carts without event taxes) must not
        // hold back the first render: the cart used to stay on the skeleton until it returned.
        this.loadTaxes();
        this.setupTimer();
        this.loadExistingPromo();
        this.render();

        // Re-render when BileteOnlineCart re-validates the promo against new cart contents. The qty-change path
        // calls BileteOnlineCart.save() → saveCart() → revalidatePromoCode() asynchronously, so by the time
        // this.render() runs the local discount snapshot is still stale; the promo event lands later.
        const onPromoChanged = () => {
            const promo = BileteOnlineCart.getPromoCode();
            this.appliedPromo = promo ? promo.code : null;
            this.render();
        };
        // cart.js dispatches `bileteonline:cart:*` events. The `ambilet:*` names are legacy aliases from the
        // ambilet marketplace; they never fire here but are kept during the rename window.
        window.addEventListener('ambilet:cart:promo', onPromoChanged);
        window.addEventListener('bileteonline:cart:promo', onPromoChanged);
        window.addEventListener('ambilet:cart:update', () => this.render());
        window.addEventListener('bileteonline:cart:update', () => this.render());
    },

    /**
     * Load taxes from cart items or use defaults from config
     */
    async loadTaxes() {
        const items = BileteOnlineCart.getItems();
        if (items.length > 0 && items[0].event?.taxes?.length > 0) {
            // Show ALL taxes (both included in price and added on top)
            this.taxes = items[0].event.taxes.filter(t => t.is_active !== false);
            return;
        }

        try {
            if (typeof BileteOnlineAPI !== 'undefined') {
                const response = await BileteOnlineAPI.get('/config/taxes');
                if (response.success && response.data?.taxes) {
                    this.taxes = response.data.taxes;
                    return;
                }
            }
        } catch (e) {
            // Falls back to empty taxes array below
        }

        // Fallback - no hardcoded taxes, they come from DB via cart items
        this.taxes = [];
    },

    // ==================== MARKUP HELPERS ====================

    esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    icon(name) {
        return '<svg class="ic" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
    },

    money(value) {
        return BileteOnlineUtils.formatCurrency(value);
    },

    /** Romanian counting: 1 bilet, 5 bilete, 20 de bilete, 101 bilete. */
    ticketsWord(n) {
        if (n === 1) return 'bilet';
        const rest = n % 100;
        return n !== 0 && (rest === 0 || rest >= 20) ? 'de bilete' : 'bilete';
    },

    /** A bare YYYY-MM-DD parses as UTC midnight; adding a local time keeps it on the booked day in any time zone. */
    localDate(value) {
        return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value + 'T00:00:00' : value;
    },

    formatDay(value, format) {
        if (!value) return '';
        try {
            const out = BileteOnlineUtils.formatDate(this.localDate(value), format);
            return out && out !== 'Invalid Date' ? out : String(value);
        } catch (e) {
            return String(value);
        }
    },

    /** Arched thumbnail: the photo sits over the brand-line fallback, which shows through when there is no photo or it fails. */
    media(src, href, seed) {
        const segs = [['1060 585 220 310', '220 / 310'], ['1455 585 290 310', '290 / 310'], ['2170 625 340 270', '340 / 270'], ['2665 625 250 270', '250 / 270']];
        let sum = 0;
        for (const ch of String(seed || '')) sum += ch.codePointAt(0);
        const seg = segs[sum % segs.length];
        const inner = '<span class="fb"><svg viewBox="' + seg[0] + '" style="aspect-ratio:' + seg[1] + '"><use href="#drum-g"/></svg></span>' +
            (src ? '<img src="' + this.esc(src) + '" alt="" loading="lazy" decoding="async" onerror="this.remove()">' : '');
        return href
            ? '<a class="ci-media" href="' + this.esc(href) + '" tabindex="-1" aria-hidden="true">' + inner + '</a>'
            : '<span class="ci-media" aria-hidden="true">' + inner + '</span>';
    },

    stepper(index, label, less, more, quantity) {
        return '<div class="ci-step" role="group" aria-label="' + label + '">' +
            '<button type="button" data-focus="dec" onclick="CartPage.updateQuantity(' + index + ', -1)" aria-label="' + less + '">−</button>' +
            '<output aria-live="polite">' + quantity + '</output>' +
            '<button type="button" data-focus="inc" onclick="CartPage.updateQuantity(' + index + ', 1)" aria-label="' + more + '">+</button>' +
        '</div>';
    },

    // ==================== RESERVATION TIMER ====================

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
            if (timerBar) {
                timerBar.classList.remove('is-warn', 'is-urgent');
                timerBar.classList.add('is-expired');
            }

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
            // Less than 1 minute - urgent
            if (timerBar) {
                timerBar.classList.remove('is-warn');
                timerBar.classList.add('is-urgent');
            }
        } else if (remaining <= 5 * 60 * 1000 && !this.warningShown) {
            // 5 minutes remaining - show warning notification
            this.warningShown = true;
            if (timerBar) timerBar.classList.add('is-warn');
            if (typeof BileteOnlineNotifications !== 'undefined') {
                BileteOnlineNotifications.warning('Mai ai doar 5 minute pentru a finaliza comanda! După expirare, locurile vor fi eliberate.');
            }
        }
    },

    /**
     * Release all held seats via API
     */
    async releaseAllSeats() {
        const items = BileteOnlineCart.getItems();

        for (const item of items) {
            if (item.seat_uids && item.seat_uids.length > 0 && item.event_seating_id) {
                try {
                    await BileteOnlineAPI.delete('/cart/seats', {
                        event_seating_id: item.event_seating_id,
                        seat_uids: item.seat_uids
                    });
                } catch (error) {
                    // Continue even if release fails - cleanup job will handle it
                }
            }
        }
    },

    // ==================== RENDER ====================

    render() {
        const items = BileteOnlineCart.getItems();

        const loading = document.getElementById('cart-loading');
        const container = document.getElementById('cartPageItems');
        const emptyState = document.getElementById('emptyCart');
        const summarySection = document.getElementById('summary-section');
        const promoSection = document.getElementById('promo-section');
        const timerBar = document.getElementById('timer-bar');

        loading.classList.add('hidden');
        const hadFocus = !!document.activeElement && container.contains(document.activeElement);

        if (items.length === 0) {
            container.classList.add('hidden');
            container.innerHTML = '';
            summarySection.classList.add('hidden');
            promoSection.classList.add('hidden');
            timerBar.classList.add('hidden');
            emptyState.classList.remove('hidden');
            // The last line was removed from the keyboard: move focus to the empty-state heading.
            if (hadFocus) {
                const heading = emptyState.querySelector('h2');
                if (heading) heading.focus();
            }
            // Reset the counters so the page doesn't show e.g. "1 bilet" next to "Coșul tău e gol".
            const totalItemsEl = document.getElementById('totalItems');
            if (totalItemsEl) totalItemsEl.textContent = '0';
            const summaryItemsEl = document.getElementById('summaryItems');
            if (summaryItemsEl) summaryItemsEl.textContent = '0';
            document.querySelectorAll('[data-items-word]').forEach((el) => { el.textContent = this.ticketsWord(0); });
            return;
        }

        emptyState.classList.add('hidden');
        container.classList.remove('hidden');
        summarySection.classList.remove('hidden');
        promoSection.classList.remove('hidden');
        timerBar.classList.remove('hidden');

        try {
            // Re-rendering replaces the buttons, so keyboard focus is carried over to the same control.
            const active = document.activeElement;
            const focusKey = active && container.contains(active) ? active.getAttribute('data-focus') : null;
            const focusCard = focusKey ? active.closest('.ci') : null;
            const focusIndex = focusCard ? focusCard.getAttribute('data-index') : null;

            container.innerHTML = items.map((item, index) => this.renderCartItem(item, index)).join('');

            if (focusKey) {
                const target = container.querySelector('.ci[data-index="' + focusIndex + '"] [data-focus="' + focusKey + '"]')
                    || [...container.querySelectorAll('[data-focus="' + focusKey + '"]')].pop();
                if (target) target.focus();
            }
            this.updateSummary();
        } catch (error) {
            // Don't swallow render failures silently — they're the only signal we have when a cart item shape
            // changes (e.g. an activity item field rename) and the page ends up empty.
            console.error('[CartPage] render failed:', error, items);
        }
    },

    /**
     * Render an activity cart line. Activities sell by (slot date + start time + variant) instead of
     * (event + ticket type), so the quantity controls double as participant-count controls.
     */
    renderActivityCartItem(item, index) {
        const itemKey = item.key || index;
        const a = item.activity || {};
        const v = item.variant || {};
        const quantity = item.participants_count || item.quantity || 1;
        const price = (typeof v.price === 'number' ? v.price : item.price) || 0;
        const lineTotal = price * quantity;

        const imgSrc = a.image ? (typeof getStorageUrl === 'function' ? getStorageUrl(a.image) : a.image) : '';
        const title = a.title || 'Activitate';
        const variantName = v.name || 'Bilet';
        const slotStart = (item.slot_start_time || '').substring(0, 5);
        const slotEnd = (item.slot_end_time || '').substring(0, 5);
        const venueLine = [a.venue, a.city].filter(Boolean).join(' · ');
        const href = a.slug ? '/activitate/' + encodeURIComponent(a.slug) : '';

        const formattedDate = this.formatDay(item.booking_date || '', 'long');
        const slotLine = slotStart
            ? `${formattedDate} · ${slotStart}${slotEnd ? '–' + slotEnd : ''}`
            : formattedDate;

        return '<article class="ci" data-item-key="' + this.esc(itemKey) + '" data-index="' + index + '">' +
            this.media(imgSrc, href, title) +
            '<div class="ci-head">' +
                '<div class="ci-text">' +
                    '<p class="ci-kicker">Activitate</p>' +
                    '<h3 class="ci-title">' + (href ? '<a href="' + href + '">' + this.esc(title) + '</a>' : this.esc(title)) + '</h3>' +
                    (slotLine ? '<p class="ci-meta">' + this.icon('calendar-blank') + '<span>' + this.esc(slotLine) + '</span></p>' : '') +
                    (venueLine ? '<p class="ci-meta">' + this.icon('map-pin') + '<span>' + this.esc(venueLine) + '</span></p>' : '') +
                '</div>' +
                '<button class="ci-remove" type="button" data-focus="remove" onclick="CartPage.removeItem(' + index + ')" aria-label="Șterge rezervarea: ' + this.esc(title) + '">' + this.icon('x') + '</button>' +
            '</div>' +
            '<div class="ci-bottom">' +
                '<span class="ci-chip">' + this.icon('ticket') + this.esc(variantName) + '</span>' +
                this.stepper(index, 'Participanți', 'Scade nr. participanți', 'Crește nr. participanți', quantity) +
                '<div class="ci-price"><small>' + this.money(price) + ' × ' + quantity + '</small> <b>' + this.money(lineTotal) + '</b></div>' +
            '</div>' +
        '</article>';
    },

    renderCartItem(item, index) {
        // Activity items take a separate card: event code below assumes a ticketType + event date.
        if (item.type === 'activity') {
            return this.renderActivityCartItem(item, index);
        }

        // Handle both BileteOnlineCart format and legacy format
        const itemKey = item.key || index;
        const imagePath = item.event?.image || item.event_image;
        const eventImage = imagePath ? getStorageUrl(imagePath) : '';
        const eventTitle = item.event?.title || item.event_title || 'Eveniment';
        const eventDate = item.event?.performance_date || item.event?.date || item.event_date || '';
        const venueName = item.event?.venue?.name || (typeof item.event?.venue === 'string' ? item.event.venue : '') || item.venue_name || '';
        const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
        const ticketDescription = item.ticketType?.description || '';
        const price = item.ticketType?.price || item.price || 0;
        const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
        const quantity = item.quantity || 1;
        const seats = item.seats || [];
        const hasSeats = seats.length > 0 || (item.seat_uids && item.seat_uids.length > 0);
        const eventSlug = item.event?.slug || '';
        const eventHref = '/bilete/' + encodeURIComponent(eventSlug);

        // Get per-ticket commission or fall back to event-level
        const commission = BileteOnlineCart.calculateItemCommission(item);
        const commissionMode = commission.mode || 'included';

        const hasDiscount = originalPrice && originalPrice > price;
        const discountPercent = hasDiscount ? Math.round((1 - price / originalPrice) * 100) : 0;
        const formattedDate = this.formatDay(eventDate, 'medium');

        // Price is always the base price; an added-on-top commission is shown in the breakdown
        let commissionAmount = 0;
        if (commissionMode === 'added_on_top') {
            commissionAmount = commission.amount;
        }
        const totalWithCommission = price + commissionAmount;

        let tip = '<b>Detalii preț bilet ' + this.esc(ticketTypeName) + '</b>' +
            '<span><em>Preț bilet</em><strong>' + this.money(price) + '</strong></span>';
        if (commissionMode === 'added_on_top' && commissionAmount > 0) {
            let commissionLabel = 'Taxe procesare';
            if (commission.type === 'percentage') {
                commissionLabel += ' (' + commission.rate + '%)';
            } else if (commission.type === 'fixed') {
                commissionLabel += ' (fix)';
            } else if (commission.type === 'both') {
                commissionLabel += ' (' + commission.rate + '% + ' + this.money(commission.fixed) + ')';
            }
            tip += '<span><em>' + commissionLabel + '</em><strong>+' + this.money(commissionAmount) + '</strong></span>' +
                '<span class="is-total"><em>Total la plată</em><strong>' + this.money(totalWithCommission) + '</strong></span>';
        }
        const tipId = 'ci-tip-' + index;

        const quantityControl = hasSeats
            ? '<div class="ci-seated"><span class="ci-qty">' + quantity + ' ' + this.ticketsWord(quantity) + '</span> ' +
                '<a class="ci-add" href="' + eventHref + '">' + this.icon('plus') + 'Adaugă locuri</a></div>'
            : this.stepper(index, 'Cantitate', 'Scade cantitatea', 'Crește cantitatea', quantity);

        const metaLine = [formattedDate, venueName].filter(Boolean).join(' · ');

        return '<article class="ci" data-item-key="' + this.esc(itemKey) + '" data-index="' + index + '">' +
            this.media(eventImage, eventSlug ? eventHref : '', eventTitle) +
            '<div class="ci-head">' +
                '<div class="ci-text">' +
                    '<p class="ci-kicker">Eveniment</p>' +
                    '<h3 class="ci-title">' + (eventSlug ? '<a href="' + eventHref + '">' + this.esc(eventTitle) + '</a>' : this.esc(eventTitle)) + '</h3>' +
                    (metaLine ? '<p class="ci-meta">' + this.icon('calendar-blank') + '<span>' + this.esc(metaLine) + '</span></p>' : '') +
                    (ticketDescription ? '<p class="ci-notes">' + this.esc(ticketDescription) + '</p>' : '') +
                    (seats.length > 0 ? '<p class="ci-seats">' + this.icon('map-pin') + '<span>' + this.esc(this.formatSeats(seats)) + '</span></p>' : '') +
                '</div>' +
                '<button class="ci-remove" type="button" data-focus="remove" onclick="CartPage.removeItem(' + index + ')" aria-label="Șterge: ' + this.esc(eventTitle) + ', ' + this.esc(ticketTypeName) + '">' + this.icon('x') + '</button>' +
            '</div>' +
            '<div class="ci-bottom">' +
                '<div class="ci-ticket">' +
                    '<span class="ci-chip">' + this.icon('ticket') + this.esc(ticketTypeName) + (hasDiscount ? '<em class="ci-off">-' + discountPercent + '%</em>' : '') + '</span>' +
                    '<button class="ci-tip-btn" type="button" aria-label="Detalii preț" aria-describedby="' + tipId + '">' +
                        '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>' +
                    '</button>' +
                    '<span class="ci-tip-box" role="tooltip" id="' + tipId + '">' + tip + '</span>' +
                '</div>' +
                quantityControl +
                '<div class="ci-price">' +
                    (hasDiscount ? '<s>' + this.money(originalPrice * quantity) + '</s> ' : '') +
                    '<b>' + this.money(price * quantity) + '</b>' +
                '</div>' +
            '</div>' +
        '</article>';
    },

    // ==================== ACTIONS ====================

    updateQuantity(index, delta) {
        const items = BileteOnlineCart.getItems();
        if (!items[index]) return;

        const item = items[index];
        const isActivity = item.type === 'activity';

        // For seated cart items quantity is bound to the picked seats — bumping qty without picking more seats
        // produced ghost tickets with no seat_uid on the checkout. Send the user back to the event page instead.
        const hasHeldSeats = Array.isArray(item.seat_uids) && item.seat_uids.length > 0;
        if (hasHeldSeats) {
            if (typeof BileteOnlineNotifications !== 'undefined') {
                BileteOnlineNotifications.warning('Pentru bilete cu locuri specifice, schimbă cantitatea din pagina evenimentului — alege/elimină locurile pe hartă.');
            }
            return;
        }

        // Activity lines count participants: participants_count is what the card, the summary and checkout read and
        // quantity is its legacy alias, so both move together (only quantity used to change and the line looked stuck).
        const currentQty = isActivity ? (item.participants_count || item.quantity || 1) : item.quantity;
        let newQty = currentQty + delta;
        const minQty = item.ticketType?.min_per_order || item.min_per_order || 1;
        const maxQty = item.ticketType?.max_per_order || item.max_per_order || item.max_quantity || 10;

        if (delta > 0 && newQty > maxQty) {
            if (typeof BileteOnlineNotifications !== 'undefined') {
                BileteOnlineNotifications.warning(`Poți cumpăra maximum ${maxQty} bilete de acest tip`);
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
            if (isActivity) items[index].participants_count = newQty;
            BileteOnlineCart.save(items);
            this.render();
        }
    },

    async removeItem(index) {
        const item = BileteOnlineCart.getItems()[index];
        if (!item) return;
        const itemEl = document.querySelector(`#cartPageItems .ci[data-index="${index}"]`);
        // A second click during the fade-out is ignored. The card is flagged rather than the button disabled: a
        // disabled button drops keyboard focus before render() can move it to the next line.
        if (itemEl) {
            if (itemEl.hasAttribute('data-removing')) return;
            itemEl.setAttribute('data-removing', '');
        }

        // Release seats if this item has them
        if (item.seat_uids && item.seat_uids.length > 0 && item.event_seating_id) {
            try {
                await BileteOnlineAPI.delete('/cart/seats', {
                    event_seating_id: item.event_seating_id,
                    seat_uids: item.seat_uids
                });
            } catch (error) {
                // Continue with removal even if API fails — cleanup job handles
            }
        }

        const finish = () => {
            // Re-read the cart and remove by key: two removals started close together must not restore each other's line.
            const items = BileteOnlineCart.getItems();
            const at = item.key ? items.findIndex((it) => it.key === item.key) : index;
            if (at >= 0 && at < items.length) items.splice(at, 1);
            BileteOnlineCart.save(items);
            this.render();

            if (items.length === 0) {
                localStorage.removeItem('cart_end_time');
                if (this.timerInterval) {
                    clearInterval(this.timerInterval);
                }
            }
        };

        if (itemEl) {
            itemEl.style.opacity = '0';
            itemEl.style.transform = 'translateX(-20px)';
            setTimeout(finish, 300);
        } else {
            finish();
        }
    },

    // ==================== SUMMARY ====================

    updateSummary() {
        const items = BileteOnlineCart.getItems();
        let baseSubtotal = 0;  // Subtotal without commission
        let totalCommission = 0;  // Total commission
        let totalItems = 0;
        let savings = 0;
        const savingsTickets = [];
        let hasAddedOnTopCommission = false;

        // Group items by event. Activities use a different item shape (item.activity + item.variant +
        // item.participants_count) than event tickets (item.event + item.ticketType + item.quantity), so we
        // normalize here before grouping.
        const eventGroups = {};
        items.forEach(item => {
            const isActivity = item.type === 'activity';

            const eventId    = isActivity
                ? ('activity-' + (item.activity?.id || item.activity_id || 'unknown'))
                : (item.eventId || item.event?.id || 'unknown');
            const eventTitle = isActivity
                ? (item.activity?.title || item.activity?.name || 'Activitate')
                : (item.event?.title || item.event?.name || 'Eveniment');
            const eventDate  = isActivity
                ? (item.booking_date || '')
                : (item.event?.performance_date || item.event?.date || item.event_date || '');
            const venueName  = isActivity
                ? (item.activity?.venue || '')
                : (item.event?.venue?.name || (typeof item.event?.venue === 'string' ? item.event.venue : '') || item.venue_name || '');
            const cityName   = isActivity
                ? (item.activity?.city || '')
                : (item.event?.city?.name || item.event?.city || item.event?.venue?.city || '');

            if (!eventGroups[eventId]) {
                eventGroups[eventId] = {
                    title: eventTitle,
                    date: eventDate,
                    venue: venueName,
                    city: cityName,
                    tickets: [],
                    subtotal: 0,
                    commission: 0,
                    isActivity: isActivity
                };
            }

            const price = isActivity
                ? ((typeof item.variant?.price === 'number' ? item.variant.price : item.price) || 0)
                : (item.ticketType?.price || item.price || 0);
            const originalPrice = isActivity
                ? (item.variant?.originalPrice || item.original_price || 0)
                : (item.ticketType?.originalPrice || item.original_price || 0);
            const ticketName = isActivity
                ? (item.variant?.name || 'Bilet')
                : (item.ticketType?.name || item.ticket_type_name || 'Bilet');
            const quantity = isActivity
                ? (item.participants_count || item.quantity || 1)
                : (item.quantity || 1);

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

            if (originalPrice && originalPrice > price) {
                savings += (originalPrice - price) * quantity;
                savingsTickets.push(ticketName);
            }
        });

        // Total = base prices + commission − discount. The live discount is read from BileteOnlineCart on every
        // render (it clamps against the subtotal and reflects the latest backend revalidation); a value cached at
        // apply time went stale after any qty change.
        const subtotalWithCommission = baseSubtotal + totalCommission;
        let liveDiscount = 0;
        try {
            liveDiscount = (typeof BileteOnlineCart !== 'undefined' && typeof BileteOnlineCart.getPromoDiscount === 'function')
                ? BileteOnlineCart.getPromoDiscount()
                : (this.discount || 0);
        } catch (e) {
            liveDiscount = this.discount || 0;
        }
        // The discount must never exceed the subtotal (with commission), or the total would go negative.
        if (liveDiscount > subtotalWithCommission) liveDiscount = subtotalWithCommission;
        this.discount = liveDiscount;
        const subtotalAfterDiscount = subtotalWithCommission - liveDiscount;

        // Payment processing fee config is pre-warmed via BileteOnlineCart.init(); if it hasn't loaded yet we
        // render now and refresh once it arrives. The fee itself is shown at checkout, not here.
        try {
            if (typeof BileteOnlineCart !== 'undefined' && typeof BileteOnlineCart.computeProcessingFee === 'function') {
                BileteOnlineCart.computeProcessingFee(subtotalAfterDiscount);
                if (!BileteOnlineCart.getPaymentFeeConfig() && BileteOnlineCart.loadPaymentFeeConfig) {
                    if (!this._feeConfigReloadScheduled) {
                        this._feeConfigReloadScheduled = true;
                        BileteOnlineCart.loadPaymentFeeConfig().then(cfg => {
                            if (cfg) this.updateSummary();
                        });
                    }
                }
            }
        } catch (e) {}

        // Cart page total = tickets + ticketing commission (− discount) ONLY. The payment transaction fee depends
        // on the chosen payment method, so it is applied & shown at checkout.
        const total = subtotalAfterDiscount;
        const points = Math.floor(total / 10);

        document.getElementById('totalItems').textContent = totalItems;
        document.getElementById('summaryItems').textContent = totalItems;
        document.querySelectorAll('[data-items-word]').forEach((el) => { el.textContent = this.ticketsWord(totalItems); });
        // Subtotal shows BASE prices only; the platform commission gets its own row.
        document.getElementById('subtotal').textContent = this.money(baseSubtotal);

        const commRow = document.getElementById('platformCommissionRow');
        if (commRow) {
            if (hasAddedOnTopCommission && totalCommission > 0) {
                commRow.classList.remove('hidden');
                document.getElementById('platformCommissionAmount').textContent = this.money(totalCommission);
                const lbl = document.getElementById('platformCommissionLabel');
                if (lbl) {
                    // Effective % from the actual amount, so carts with different rates show an average that
                    // customers can still verify.
                    const ratePct = baseSubtotal > 0
                        ? (totalCommission / baseSubtotal * 100).toFixed(1).replace(/\.0$/, '').replace('.', ',')
                        : '';
                    lbl.textContent = 'Comision ticketing' + (ratePct ? ' (' + ratePct + '%)' : '');
                }
            } else {
                commRow.classList.add('hidden');
            }
        }

        // The payment transaction fee is NOT shown on the cart page (it depends on the payment method chosen at checkout).
        const feeRow = document.getElementById('processingFeeRow');
        if (feeRow) feeRow.classList.add('hidden');

        // Breakdown, grouped by event or activity
        const taxesContainer = document.getElementById('taxesContainer');
        if (taxesContainer) {
            let breakdownHtml = '';
            const eventIds = Object.keys(eventGroups);
            const hasMultipleEvents = eventIds.length > 1;

            eventIds.forEach((eventId, eventIndex) => {
                const group = eventGroups[eventId];

                // Show the title only when the cart spans several events
                if (hasMultipleEvents) {
                    if (eventIndex > 0) {
                        breakdownHtml += '<hr class="cs-sep">';
                    }
                    const details = [];
                    if (group.date) details.push(this.formatDay(group.date, 'short'));
                    if (group.venue) details.push(group.venue);
                    if (group.city && group.city !== group.venue) details.push(group.city);
                    breakdownHtml += '<p class="cs-group">' + this.esc(group.title) +
                        (details.length > 0 ? ' <span>(' + this.esc(details.join(', ')) + ')</span>' : '') + '</p>';
                }

                group.tickets.forEach((ticket) => {
                    breakdownHtml += '<div class="cs-line"><span>' + ticket.qty + ' × ' + this.esc(ticket.name) + '</span>' +
                        '<strong>' + this.money(ticket.lineTotal) + '</strong></div>';
                });
            });

            taxesContainer.innerHTML = breakdownHtml;
        }

        document.getElementById('totalPrice').textContent = this.money(total);

        if (liveDiscount > 0) {
            document.getElementById('discountRow').classList.remove('hidden');
            document.getElementById('discountAmount').textContent = `-${this.money(liveDiscount)}`;
        } else {
            document.getElementById('discountRow').classList.add('hidden');
        }

        if (savings > 0) {
            document.getElementById('savingsRow').classList.remove('hidden');
            document.getElementById('savings').textContent = this.money(savings);
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

        const grouped = {};
        seats.forEach(seat => {
            const key = (seat.section || 'Secțiune') + ' - Rând ' + (seat.row || '?');
            if (!grouped[key]) grouped[key] = [];
            grouped[key].push(seat.seat || seat.label || '?');
        });

        const parts = [];
        Object.keys(grouped).forEach(key => {
            parts.push(key + ': Loc ' + grouped[key].join(', '));
        });

        return parts.join(' | ');
    },

    // ==================== PROMO CODE ====================

    // Markup for the applied-promo message, with a button that removes the code.
    renderAppliedPromoMessage(promo, prefix) {
        const label = promo.type === 'percentage'
            ? `-${promo.value}% reducere`
            : `-${this.money(promo.value)} reducere`;
        const appliedTo = promo.appliedToLabel
            ? `<small>Aplicat pe: ${this.esc(promo.appliedToLabel)}</small>`
            : '';
        const codeSuffix = prefix.includes(':') ? '' : ` (${this.esc(promo.code)})`;
        return '<span class="co-promo-applied">' + this.icon('check-circle') +
            `<span>${this.esc(prefix)} ${this.esc(label)}${codeSuffix}${appliedTo}</span>` +
            '<button class="co-promo-x" type="button" onclick="CartPage.removePromo()" aria-label="Elimină codul promoțional" title="Elimină codul">' + this.icon('x') + '</button>' +
        '</span>';
    },

    async applyPromo() {
        const input = document.getElementById('promoCode');
        const code = input.value.trim().toUpperCase();
        const messageEl = document.getElementById('promoMessage');
        const btn = document.querySelector('#promo-section button');
        if (btn && btn.disabled) return;

        if (!code) {
            messageEl.textContent = 'Te rugăm să introduci un cod promoțional';
            messageEl.className = 'co-promo-msg';
            input.focus();
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Se verifică...'; }

        const result = await BileteOnlineCart.applyPromoCode(code);

        if (result.success) {
            const promo = result.promo;
            this.discount = BileteOnlineCart.getPromoDiscount();
            this.appliedPromo = code;

            messageEl.innerHTML = this.renderAppliedPromoMessage(promo, 'Cod aplicat!');
            messageEl.className = 'co-promo-msg is-ok';

            input.disabled = true;
            if (btn) { btn.textContent = 'Aplicat'; btn.disabled = true; }

            this.updateSummary();
        } else {
            messageEl.textContent = '✗ ' + (result.message || 'Cod invalid sau expirat');
            messageEl.className = 'co-promo-msg is-err';
            if (btn) { btn.disabled = false; btn.textContent = 'Aplică'; }
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
            messageEl.className = 'co-promo-msg hidden';
        }
        const btn = document.querySelector('#promo-section button');
        if (btn) { btn.textContent = 'Aplică'; btn.disabled = false; }
        const input = document.getElementById('promoCode');
        if (input) { input.value = ''; input.disabled = false; input.focus(); }

        this.updateSummary();
    },

    loadExistingPromo() {
        const promo = BileteOnlineCart.getPromoCode();
        if (!promo) return;

        // An empty cart with a promo left in storage is stale (a finished order) — wipe it so the new order starts fresh.
        if (BileteOnlineCart.getItemCount() === 0) {
            BileteOnlineCart.removePromoCode();
            return;
        }

        this.discount = BileteOnlineCart.getPromoDiscount();
        this.appliedPromo = promo.code;

        const messageEl = document.getElementById('promoMessage');
        if (messageEl) {
            messageEl.innerHTML = this.renderAppliedPromoMessage(promo, `Cod aplicat: ${promo.code}`);
            messageEl.className = 'co-promo-msg is-ok';
        }
        const input = document.getElementById('promoCode');
        if (input) { input.value = promo.code; input.disabled = true; }
        const btn = document.querySelector('#promo-section button');
        if (btn) { btn.textContent = 'Aplicat'; btn.disabled = true; }
    }
};

document.addEventListener('DOMContentLoaded', () => CartPage.init());

// Cart expiration event from cart.js (`ambilet:` alias kept for the legacy listener).
function _onCartExpired() {
    if (CartPage.timerInterval) {
        clearInterval(CartPage.timerInterval);
    }
    localStorage.removeItem('cart_end_time');
    CartPage.render();
}
window.addEventListener('ambilet:cart:expired', _onCartExpired);
window.addEventListener('bileteonline:cart:expired', _onCartExpired);
