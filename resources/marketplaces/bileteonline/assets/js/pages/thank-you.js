/**
 * bilete.online — order confirmation (/multumim, alias /thank-you).
 *
 * Loads the order from the order-confirmation API and renders thank-you.php (v2 markup; assets/v2/css/thank-you.css).
 * The page state lives on #main.ty (loading, success, pending, failed, notfound) and thank-you.css shows the blocks of
 * that state. A pending payment is checked again every 5 seconds for a minute, so a processor confirmation that lands
 * a little later turns into the success page without a manual refresh.
 */
const ThankYouPage = {
    order: null,
    orderRef: '',
    pendingChecks: 0,

    async init() {
        await this.loadOrderData();
    },

    isPaymentFailed() {
        if (!this.order) return false;
        const status = this.order.status;
        const paymentStatus = this.order.payment_status;
        return status === 'failed' || status === 'cancelled' || status === 'expired'
            || paymentStatus === 'failed' || paymentStatus === 'declined' || paymentStatus === 'expired';
    },

    isPending() {
        if (!this.order) return false;
        return this.order.status === 'pending' || this.order.payment_status === 'pending';
    },

    /**
     * Fire trackPurchase ONCE per successful order load. Uses "purchase_<orderId>" as the deterministic
     * client_event_id so the browser pixel firing here dedupes with the server-side Layer C Purchase event
     * (SendFacebookCapiPurchaseJob uses the same id).
     */
    fireTrackPurchase() {
        try {
            if (!window.EPASTracking || typeof EPASTracking.trackPurchase !== 'function') return;
            const order = this.order || {};
            const orderId = order.id;
            if (!orderId) return;
            const eventId = order.marketplace_event_id
                || order.event_id
                || (Array.isArray(order.items) && order.items[0]?.marketplace_event_id)
                || (Array.isArray(order.items) && order.items[0]?.event_id)
                || null;
            const total = parseFloat(order.total || order.total_amount || 0);
            const currency = order.currency || 'RON';
            // Forward customer identity so the backend can link this visitor to their email (ROAS attribution).
            EPASTracking.trackPurchase(eventId, orderId, total, currency, {
                client_event_id: 'purchase_' + orderId,
                content_name: order.event_name || null,
                email: order.customer_email || order.email || null,
                customer_email: order.customer_email || order.email || null,
                customer_name: order.customer_name || null,
                customer_phone: order.customer_phone || null,
            });
        } catch (e) {
            // tracking must never break the thank-you page
        }
    },

    // ==================== HELPERS ====================

    esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    icon(name) {
        return '<svg class="ic" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
    },

    money(value) {
        return BileteOnlineUtils.formatCurrency(parseFloat(value) || 0);
    },

    /** Romanian counting: 1 bilet, 5 bilete, 20 de bilete, 101 bilete. */
    ticketsWord(n) {
        if (n === 1) return 'bilet';
        const rest = n % 100;
        return n !== 0 && (rest === 0 || rest >= 20) ? 'de bilete' : 'bilete';
    },

    notify(type, message) {
        if (typeof BileteOnlineNotifications !== 'undefined') {
            BileteOnlineNotifications[type](message);
        }
    },

    setState(state) {
        const root = document.querySelector('.ty');
        if (root) root.setAttribute('data-state', state);
    },

    setHero(title, text) {
        const heading = document.getElementById('ty-title');
        if (heading && title) heading.textContent = title;
        const printingText = document.getElementById('printingText');
        if (printingText && text) printingText.textContent = text;
    },

    setStatus(label) {
        const badge = document.getElementById('orderStatus');
        if (badge) badge.textContent = label;
    },

    /** Venue may be a string or a translatable object {ro: "...", en: "..."}. */
    venueName(event) {
        if (!event || !event.venue) return '';
        if (typeof event.venue === 'object') return event.venue.ro || event.venue.en || Object.values(event.venue)[0] || '';
        return event.venue;
    },

    eventTime(event) {
        if (!event) return '';
        if (event.time) return String(event.time).substring(0, 5);
        // doors_open is either a clock time or a full ISO date (marketplace events): only the former is a time
        if (typeof event.doors_open === 'string' && /^\d{2}:\d{2}/.test(event.doors_open)) return event.doors_open.substring(0, 5);
        if (event.date && String(event.date).includes('T')) {
            try {
                const time = new Date(event.date).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Bucharest' });
                return time === '00:00' ? '' : time; // a date without a start time
            } catch (e) {}
        }
        return '';
    },

    shareUrl() {
        const slug = this.order?.event?.slug;
        return slug ? window.location.origin + '/bilete/' + slug : window.location.origin + '/';
    },

    /** Buttons that replace the "back home" link on the failed and not-found pages. */
    renderBack(links) {
        const backSection = document.getElementById('backSection');
        if (!backSection) return;
        backSection.innerHTML = links.map((link) =>
            '<a href="' + this.esc(link.href) + '" class="btn ' + (link.primary ? 'btn-primary' : 'btn-ghost') + '">' +
                (link.icon ? this.icon(link.icon) : '') + this.esc(link.label) +
            '</a>'
        ).join('');
    },

    createConfetti() {
        const container = document.getElementById('confetti');
        if (!container) return;
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const colors = ['#2BB673', '#F2A900', '#E43A33', '#2D6CCD', '#9FE0BF', '#1B7F4E'];

        for (let i = 0; i < 80; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 3) + 's';
                if (Math.random() > 0.5) {
                    confetti.style.borderRadius = '50%';
                }
                container.appendChild(confetti);
                setTimeout(() => confetti.remove(), 6000);
            }, i * 50);
        }
    },

    // ==================== LOAD ====================

    async loadOrderData() {
        const urlParams = new URLSearchParams(window.location.search);
        // Read from 'order' param (our param) or 'orderId' (Netopia adds this on redirect)
        const orderRef = urlParams.get('order') || urlParams.get('orderId');

        if (!orderRef) {
            this.renderOrderNotFound();
            return;
        }
        this.orderRef = orderRef;

        // Clean up duplicate URL params (keep only ?order=)
        if (urlParams.has('orderId')) {
            const cleanUrl = window.location.pathname + '?order=' + encodeURIComponent(orderRef);
            history.replaceState(null, '', cleanUrl);
        }

        try {
            const response = await BileteOnlineAPI.get(`/order-confirmation/${orderRef}`);
            if (response.success && response.data?.order) {
                this.order = response.data.order;
                this.renderForStatus();
            } else {
                console.warn('Order data not found in response:', response);
                this.renderOrderNotFound();
            }
        } catch (error) {
            console.error('Failed to load order:', error);
            this.renderOrderNotFound();
        }
    },

    renderForStatus() {
        if (this.isPaymentFailed()) {
            this.renderFailedPayment();
        } else if (this.isPending()) {
            this.renderPendingPayment();
        } else {
            this.createConfetti();
            this.renderOrderData();
            this.fireTrackPurchase();
        }
    },

    /** While the payment is pending, ask again every 5 s for a minute; stop at the first final status. */
    schedulePendingCheck() {
        if (this.pendingChecks >= 12 || !this.orderRef) return;
        this.pendingChecks++;
        setTimeout(async () => {
            try {
                // noCache: BileteOnlineAPI keeps GET responses in memory, which would return the first "pending" again
                const response = await BileteOnlineAPI.request(`/order-confirmation/${this.orderRef}`, { method: 'GET', noCache: true });
                const order = response?.success ? response.data?.order : null;
                if (!order) {
                    this.schedulePendingCheck();
                    return;
                }
                this.order = order;
                this.renderForStatus();
            } catch (e) {
                this.schedulePendingCheck();
            }
        }, 5000);
    },

    // ==================== STATES ====================

    renderOrderNotFound() {
        this.setState('notfound');
        this.setHero(
            'Comanda nu a fost găsită',
            'Nu am putut găsi informații despre această comandă. Verifică numărul comenzii sau contactează suportul.'
        );
        this.renderBack([
            { href: '/cont/comenzi', label: 'Comenzile mele', primary: true, icon: 'ticket' },
            { href: '/', label: 'Pagina principală', icon: 'arrow-left' },
        ]);
    },

    renderFailedPayment() {
        this.setState('failed');
        this.setHero(
            'Plata nu a fost procesată',
            'Din păcate, plata nu a putut fi finalizată. Te rugăm să verifici datele cardului și să încerci din nou.'
        );
        this.setStatus('Eșuată');

        // Still render order details (event info, payment info) so the customer sees what they tried to buy
        this.renderOrderDetails();

        const eventSlug = this.order?.event?.slug;
        const retryUrl = eventSlug ? '/bilete/' + eventSlug : '/';
        this.renderBack([
            { href: retryUrl, label: 'Încearcă din nou', primary: true },
            { href: '/', label: 'Pagina principală', icon: 'arrow-left' },
        ]);
    },

    renderPendingPayment() {
        this.setState('pending');
        this.setHero(
            'Plata este în procesare',
            'Plata ta este în curs de verificare. Vei primi biletele pe email imediat ce plata este confirmată.'
        );
        const emailTitle = document.getElementById('emailCardTitle');
        if (emailTitle) emailTitle.textContent = 'Vei primi biletele pe email după confirmarea plății';
        const buyerEmail = document.getElementById('buyerEmail');
        if (buyerEmail) buyerEmail.textContent = this.order?.customer_email || '';
        this.setStatus('În așteptare');

        this.renderOrderDetails();
        this.schedulePendingCheck();
    },

    renderOrderData() {
        const order = this.order;

        this.setState('success');
        this.setHero('Biletele tale sunt gata.', 'Plata a fost confirmată și biletele au fost emise.');
        const emailTitle = document.getElementById('emailCardTitle');
        if (emailTitle) emailTitle.textContent = 'Biletele au fost trimise pe email';
        document.getElementById('buyerEmail').textContent = order.customer_email || 'Email-ul tău';
        this.setStatus('Confirmată');

        this.renderEventInfo();

        const tickets = order.tickets || [];
        this.renderTickets(Array.isArray(tickets) ? tickets : Object.values(tickets));

        this.renderTicketsSummary('Bilete achiziționate', true);
        this.renderPaymentSummary('Total plătit');
        this.renderPaymentMethod();
        this.renderThankYouMessage();

        // Loyalty points the order earns (credited after the activity; the order says how many)
        const pointsEl = document.getElementById('pointsEarned');
        const toEarn = Math.max(0, parseInt(order.points_to_earn, 10) || 0);
        if (pointsEl) {
            pointsEl.hidden = toEarn <= 0;
            if (toEarn > 0) {
                const word = toEarn === 1 ? '1 punct' : new Intl.NumberFormat('ro-RO').format(toEarn) + (toEarn % 100 >= 20 || toEarn % 100 === 0 ? ' de puncte' : ' puncte');
                document.getElementById('earnedPoints').textContent = '+' + new Intl.NumberFormat('ro-RO').format(toEarn);
                document.getElementById('pointsTitle').textContent = 'Câștigi ' + word + ' cu această comandă';
            }
        }

        // Download button
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn && !downloadBtn.dataset.bound) {
            downloadBtn.dataset.bound = '1';
            downloadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                if (this.order?.can_download_tickets) this.downloadTickets();
            });
        }
        if (downloadBtn) {
            if (order.can_download_tickets) downloadBtn.removeAttribute('aria-disabled');
            else downloadBtn.setAttribute('aria-disabled', 'true');
        }

        // Calendar button (only when the order has an event with a date)
        const calendarBtn = document.getElementById('calendarBtn');
        if (calendarBtn) {
            if (order.event && order.event.date) {
                calendarBtn.classList.remove('hidden');
                if (!calendarBtn.dataset.bound) {
                    calendarBtn.dataset.bound = '1';
                    calendarBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.addToCalendar();
                    });
                }
            } else {
                calendarBtn.classList.add('hidden');
            }
        }

        // Share buttons
        const url = this.shareUrl();
        const title = order.event?.name || order.event?.title || (window.BILETEONLINE?.siteName || 'bilete.online');
        const shareFb = document.getElementById('shareFb');
        if (shareFb) {
            shareFb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
            shareFb.target = '_blank';
        }
        const shareWa = document.getElementById('shareWa');
        if (shareWa) {
            shareWa.href = 'https://wa.me/?text=' + encodeURIComponent(title + ' - ' + url);
            shareWa.target = '_blank';
        }
    },

    /** Order details for the failed and pending pages. */
    renderOrderDetails() {
        if (!this.order) return;
        this.renderEventInfo();
        this.renderTicketsSummary('Bilete', false);
        this.renderPaymentSummary('Total');
        this.renderPaymentMethod();
        this.renderThankYouMessage();

        const pointsEl = document.getElementById('pointsEarned');
        if (pointsEl) pointsEl.hidden = true;
    },

    // ==================== BLOCKS ====================

    renderEventInfo() {
        const eventInfo = document.getElementById('eventInfo');
        const event = this.order?.event;
        if (!eventInfo) return;
        if (!event) {
            // Activity bookings come without an event block; the order carries its first booking instead
            const act = this.order?.activity;
            if (!act) {
                eventInfo.classList.add('hidden');
                return;
            }
            const actPlace = [act.location?.name, act.location?.city].filter(Boolean).join(', ');
            const actImg = act.image ? getStorageUrl(act.image) : '';
            eventInfo.classList.remove('hidden');
            eventInfo.innerHTML =
                '<span class="ty-event-media" aria-hidden="true">' +
                    '<span class="fb"><svg viewBox="1455 585 290 310" style="aspect-ratio:290 / 310"><use href="#drum-g"/></svg></span>' +
                    (actImg ? '<img src="' + this.esc(actImg) + '" alt="" loading="lazy" onerror="this.remove()">' : '') +
                '</span>' +
                '<div>' +
                    '<h3>' + this.esc(act.title || 'Rezervare') + '</h3>' +
                    (act.date_label ? '<p>' + this.icon('calendar-blank') + '<span>' + this.esc(act.date_label) + '</span></p>' : '') +
                    (actPlace ? '<p>' + this.icon('map-pin') + '<span>' + this.esc(actPlace) + '</span></p>' : '') +
                '</div>';
            return;
        }

        const eventTitle = event.name || event.title || 'Eveniment';
        const eventDate = event.date ? BileteOnlineUtils.formatDate(event.date) : '';
        const eventTime = this.eventTime(event);
        const place = [this.venueName(event), event.city].filter(Boolean).join(', ');
        const when = [eventDate, eventTime].filter(Boolean).join(' · ');
        const img = event.image ? getStorageUrl(event.image) : '';

        eventInfo.classList.remove('hidden');
        eventInfo.innerHTML =
            '<span class="ty-event-media" aria-hidden="true">' +
                '<span class="fb"><svg viewBox="1455 585 290 310" style="aspect-ratio:290 / 310"><use href="#drum-g"/></svg></span>' +
                (img ? '<img src="' + this.esc(img) + '" alt="" loading="lazy" onerror="this.remove()">' : '') +
            '</span>' +
            '<div>' +
                '<h3>' + this.esc(eventTitle) + '</h3>' +
                (when ? '<p>' + this.icon('calendar-blank') + '<span>' + this.esc(when) + '</span></p>' : '') +
                (place ? '<p>' + this.icon('map-pin') + '<span>' + this.esc(place) + '</span></p>' : '') +
            '</div>';
    },

    renderTicketsSummary(heading, withExtras) {
        const order = this.order;
        const ticketsSummary = document.getElementById('ticketsSummary');
        if (!ticketsSummary) return;
        if (!order.items || order.items.length === 0) {
            ticketsSummary.classList.add('hidden');
            return;
        }

        let html = '<p class="ty-sub-h">' + this.esc(heading) + '</p>';
        html += order.items.map(item =>
            '<div class="ty-item">' +
                '<span><b>' + this.esc(item.name) + '</b> <small>× ' + this.esc(item.quantity) + '</small>' +
                    (item.date_label ? '<small class="ty-item-when">' + this.esc([item.date_label, item.time_label].filter(Boolean).join(', ')) + '</small>' : '') +
                '</span>' +
                '<strong>' + this.money(item.total) + '</strong>' +
            '</div>'
        ).join('');

        if (withExtras) {
            const tickets = Array.isArray(order.tickets) ? order.tickets : Object.values(order.tickets || {});
            const insuredTickets = tickets.filter(t => t.has_insurance);
            if (insuredTickets.length > 0) {
                const n = insuredTickets.length;
                html += '<div class="ty-extra"><p class="ty-insured">' + this.icon('check-circle') +
                    n + ' ' + (n === 1 ? 'bilet asigurat' : 'bilete asigurate') + ' cu taxa de retur</p></div>';
            }
            const seatedTickets = tickets.filter(t => t.seat);
            if (seatedTickets.length > 0) {
                html += '<div class="ty-extra"><p class="ty-sub-h">Locuri atribuite</p>' +
                    seatedTickets.map(t => {
                        const seat = [t.seat.section_name, t.seat.row_label ? 'Rând ' + t.seat.row_label : '', t.seat.seat_number ? 'Loc ' + t.seat.seat_number : ''].filter(Boolean).join(', ') || t.seat.label || '';
                        return '<p class="ty-seat"><span>' + this.esc(t.type || 'Bilet') + '</span><b>' + this.esc(seat) + '</b></p>';
                    }).join('') +
                '</div>';
            }
        }

        ticketsSummary.classList.remove('hidden');
        ticketsSummary.innerHTML = html;
    },

    renderPaymentSummary(totalLabel) {
        const order = this.order;
        const subtotal = parseFloat(order.subtotal) || 0;
        const total = parseFloat(order.total) || 0;
        const discount = parseFloat(order.discount) || 0;
        const serviceFee = parseFloat(order.service_fee) || 0;
        const insuranceAmount = parseFloat(order.insurance_amount) || 0;

        // Split the "service fee" into the charges the cart showed separately when the order carries them
        // (platform commission added on top, card processing fee); older orders keep one "Comision serviciu" line.
        const meta = order.meta || {};
        const commissionAddedOnTop = parseFloat(meta.commission_added_on_top ?? order.commission_added_on_top ?? 0) || 0;
        const processingFee = (typeof order.processing_fee_cents === 'number')
            ? order.processing_fee_cents / 100
            : (parseFloat(order.processing_fee) || 0);
        const hasSplit = commissionAddedOnTop > 0 || processingFee > 0;
        // Rounding or unaccounted fees stay visible under "Alte taxe" so the lines still add up to the total
        const otherFees = Math.max(0, +(serviceFee - commissionAddedOnTop - processingFee).toFixed(2));

        const row = (label, amount, cls) => '<div class="ty-row' + (cls ? ' ' + cls : '') + '"><span>' + label + '</span><strong>' + amount + '</strong></div>';
        let rows = row('Subtotal', this.money(subtotal));
        if (hasSplit) {
            if (commissionAddedOnTop > 0) rows += row('Comision platformă', this.money(commissionAddedOnTop));
            if (processingFee > 0) rows += row('Taxă procesare card', this.money(processingFee));
            if (otherFees > 0) rows += row('Alte taxe', this.money(otherFees));
        } else if (serviceFee > 0) {
            rows += row('Comision serviciu', this.money(serviceFee));
        }
        if (insuranceAmount > 0) rows += row('Taxa de retur', this.money(insuranceAmount));
        if (discount > 0) rows += row('Reducere', '-' + this.money(discount), 'is-disc');
        const pointsDiscount = parseFloat(order.points_discount) || 0;
        const pointsUsed = parseInt(order.points_used, 10) || 0;
        if (pointsDiscount > 0) rows += row('Plătit cu ' + new Intl.NumberFormat('ro-RO').format(pointsUsed) + ' puncte', '-' + this.money(pointsDiscount), 'is-disc');
        rows += row(this.esc(totalLabel), this.money(total), 'is-total');

        document.getElementById('paymentSummary').innerHTML = '<p class="ty-sub-h">Sumar plată</p><div class="ty-lines">' + rows + '</div>';
    },

    renderPaymentMethod() {
        const order = this.order;
        if (order.payment_method) {
            const cardEl = document.getElementById('cardNumber');
            if (cardEl) cardEl.textContent = order.payment_method;
        }
        // Processor badge reflects the processor that handled this order (STRIPE by default)
        const badgeText = document.getElementById('paymentProcessorBadgeText');
        if (badgeText && order.payment_processor) {
            badgeText.textContent = String(order.payment_processor).replace(/^payment-/, '').toUpperCase();
        }
    },

    /** The organizer's post-purchase message; sanitized with HTMLPurifier when it is saved. */
    renderThankYouMessage() {
        const box = document.getElementById('thankYouMessage');
        const body = document.getElementById('thankYouMessageBody');
        if (!box || !body) return;
        const html = this.order?.event?.thank_you_message;
        if (typeof html === 'string' && html.trim() !== '') {
            body.innerHTML = html;
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
        }
    },

    // ==================== ACTIONS ====================

    downloadTickets() {
        const order = this.order;
        if (!order || !order.order_number) return;

        // Backend PDF endpoint, rendered with the event's ticket template (or the marketplace default)
        window.location.href = '/api/proxy.php?action=order.download-tickets-pdf&order='
            + encodeURIComponent(order.order_number);
    },

    /** Client-side print of the tickets (kept as a fallback; the PDF endpoint above is the default). */
    downloadTicketsLegacyPrint() {
        const order = this.order;
        if (!order || !order.tickets) return;

        const esc = (v) => this.esc(v);
        const event = order.event;
        const eventTitle = event?.name || event?.title || 'Eveniment';
        const eventDate = event?.date ? BileteOnlineUtils.formatDate(event.date) : '';
        const venue = this.venueName(event);
        const siteName = window.BILETEONLINE?.siteName || 'bilete.online';

        const ticketsHtml = order.tickets.map((ticket, idx) => {
            const seatInfo = ticket.seat ? [
                ticket.seat.section_name,
                ticket.seat.row_label ? 'Rând ' + ticket.seat.row_label : '',
                ticket.seat.seat_number ? 'Loc ' + ticket.seat.seat_number : ''
            ].filter(Boolean).join(' | ') : '';
            const code = ticket.code || ticket.barcode || '';

            return `
                <div style="page-break-inside: avoid; border: 2px solid #0E4837; border-radius: 12px; padding: 24px; margin-bottom: 24px; max-width: 500px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px dashed #DEDED8;">
                        <div>
                            <div style="font-size: 11px; color: #5F6360; text-transform: uppercase;">${esc(siteName)}</div>
                            <div style="font-size: 18px; font-weight: 700;">${esc(ticket.type || 'Bilet')}</div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: #5F6360;">${idx + 1} / ${order.tickets.length}</div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; color: #5F6360;">EVENIMENT</div>
                        <div style="font-size: 16px; font-weight: 600;">${esc(eventTitle)}</div>
                    </div>
                    <div style="display: flex; gap: 24px; margin-bottom: 12px;">
                        <div><div style="font-size: 11px; color: #5F6360;">DATA</div><div style="font-weight: 600;">${esc(eventDate)}</div></div>
                        <div><div style="font-size: 11px; color: #5F6360;">LOCAȚIE</div><div style="font-weight: 600;">${esc(venue)}${event?.city ? ', ' + esc(event.city) : ''}</div></div>
                    </div>
                    ${seatInfo ? `<div style="margin-bottom: 12px; padding: 8px 12px; background: #EDEDE9; border-radius: 8px; font-weight: 600;">${esc(seatInfo)}</div>` : ''}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div><div style="font-size: 11px; color: #5F6360;">PARTICIPANT</div><div style="font-weight: 500;">${esc(ticket.attendee_name || order.customer_name || '')}</div></div>
                        <div style="text-align: right;"><div style="font-size: 11px; color: #5F6360;">PREȚ</div><div style="font-weight: 700; color: #1E5B48;">${BileteOnlineUtils.formatCurrency(ticket.price)}</div></div>
                    </div>
                    <div style="text-align: center; padding-top: 12px; border-top: 1px solid #DEDED8;">
                        ${code ? `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(code)}" style="width: 150px; height: 150px;" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" />
                        <div style="display:none;padding:10px;border:2px solid #0E4837;border-radius:8px;font-family:monospace;font-size:14px;font-weight:bold;word-break:break-all">${esc(code)}</div>` : '<div style="padding:10px;color:#6B6F6C;font-size:12px;">Cod indisponibil</div>'}
                        <div style="font-family: monospace; font-size: 11px; color: #5F6360; margin-top: 6px;">${esc(code)}</div>
                        ${ticket.ticket_series ? `<div style="font-family: monospace; font-size: 10px; color: #6B6F6C; margin-top: 2px;">Serie: ${esc(ticket.ticket_series)}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');

        const printWindow = window.open('', '_blank');
        if (!printWindow) return;
        printWindow.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bilete - ${esc(order.order_number)}</title>
            <style>body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; color: #212121; }
            @media print { body { padding: 0; } }</style></head>
            <body><div style="max-width: 500px; margin: 0 auto;">${ticketsHtml}</div>
            <scr` + `ipt>
            // Wait for QR code images to load before printing
            function waitForImages() {
                var imgs = document.querySelectorAll('img');
                var loaded = 0;
                var total = imgs.length;
                if (total === 0) { window.print(); return; }
                imgs.forEach(function(img) {
                    if (img.complete) { loaded++; if (loaded >= total) window.print(); }
                    else {
                        img.onload = function() { loaded++; if (loaded >= total) window.print(); };
                        img.onerror = function() { loaded++; if (loaded >= total) window.print(); };
                    }
                });
                // Fallback: print after 3 seconds even if images haven't loaded
                setTimeout(function() { window.print(); }, 3000);
            }
            waitForImages();
            <\/scr` + `ipt></body></html>`);
        printWindow.document.close();
    },

    addToCalendar() {
        const event = this.order?.event;
        if (!event) return;

        const title = event.name || event.title || 'Eveniment';
        const venue = this.venueName(event);
        const location = venue + (event.city ? ', ' + event.city : '');
        const startDate = event.date ? new Date(event.date) : null;

        if (!startDate || isNaN(startDate.getTime())) {
            this.notify('info', 'Data evenimentului nu este disponibilă.');
            return;
        }

        // Google Calendar format (YYYYMMDDTHHmmssZ)
        const formatGCal = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        const endDate = new Date(startDate.getTime() + 3 * 60 * 60 * 1000); // +3 hours default

        const gcalUrl = 'https://www.google.com/calendar/render?action=TEMPLATE'
            + '&text=' + encodeURIComponent(title)
            + '&dates=' + formatGCal(startDate) + '/' + formatGCal(endDate)
            + '&location=' + encodeURIComponent(location)
            + '&details=' + encodeURIComponent('Bilete achiziționate pe ' + (window.BILETEONLINE?.siteName || 'bilete.online'));

        window.open(gcalUrl, '_blank', 'noopener');
    },

    renderTickets(tickets) {
        const container = document.getElementById('ticketsScroll');
        const indicators = document.getElementById('scrollIndicators');
        const prev = document.getElementById('ticketsPrev');
        const next = document.getElementById('ticketsNext');
        const total = tickets.length;

        if (total === 0) {
            document.getElementById('ticketsCount').textContent = 'Nu există bilete';
            container.innerHTML = '';
            indicators.innerHTML = '';
            return;
        }

        const eventName = this.order?.event?.name || this.order?.event?.title;
        document.getElementById('ticketsCount').textContent =
            `${total} ${this.ticketsWord(total)} ${eventName ? 'pentru ' + eventName : 'în această comandă'}`;

        container.innerHTML = tickets.map((ticket, idx) => this.renderTicketCard(ticket, idx, total)).join('');
        indicators.innerHTML = total > 1
            ? tickets.map((_, idx) => `<button type="button" class="scroll-dot${idx === 0 ? ' active' : ''}" data-index="${idx}" aria-label="Biletul ${idx + 1} din ${total}"></button>`).join('')
            : '';

        const step = () => (container.querySelector('.tk')?.offsetWidth || 300) + 16;
        const sync = () => {
            const activeIndex = Math.min(total - 1, Math.max(0, Math.round(container.scrollLeft / step())));
            indicators.querySelectorAll('.scroll-dot').forEach((dot, i) => {
                dot.classList.toggle('active', i === activeIndex);
                if (i === activeIndex) dot.setAttribute('aria-current', 'true');
                else dot.removeAttribute('aria-current');
            });
            if (prev && next) {
                prev.disabled = activeIndex === 0;
                next.disabled = activeIndex >= total - 1;
            }
        };

        if (this._carouselBound) {
            sync();
            return;
        }
        this._carouselBound = true;

        container.addEventListener('scroll', sync, { passive: true });

        indicators.addEventListener('click', (e) => {
            const dot = e.target.closest('.scroll-dot');
            if (!dot) return;
            container.scrollTo({ left: parseInt(dot.dataset.index, 10) * step(), behavior: 'smooth' });
        });

        if (prev && next && total > 1) {
            prev.hidden = false;
            next.hidden = false;
            prev.addEventListener('click', () => container.scrollBy({ left: -step(), behavior: 'smooth' }));
            next.addEventListener('click', () => container.scrollBy({ left: step(), behavior: 'smooth' }));
        }

        // Mouse drag support for desktop
        let isDragging = false, startX = 0, scrollStart = 0;
        container.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.pageX;
            scrollStart = container.scrollLeft;
            container.classList.add('dragging');
        });
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            container.scrollLeft = scrollStart - (e.pageX - startX);
        });
        document.addEventListener('mouseup', () => {
            if (!isDragging) return;
            isDragging = false;
            container.classList.remove('dragging');
        });

        // Keyboard arrow support
        container.setAttribute('tabindex', '0');
        container.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') { e.preventDefault(); container.scrollBy({ left: step(), behavior: 'smooth' }); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); container.scrollBy({ left: -step(), behavior: 'smooth' }); }
        });

        sync();
    },

    renderTicketCard(ticket, idx, total) {
        // Decorative bars; the scannable QR code is in the email and the PDF
        const bars = Array.from({ length: 24 }, () => `<i style="height:${16 + Math.round(Math.random() * 20)}px"></i>`).join('');

        const event = this.order?.event;
        const act = ticket.activity || null; // activities module: product, date and place of this ticket
        const eventTitle = act ? [act.name, act.package ? '(' + act.package + ')' : ''].filter(Boolean).join(' ') : (event?.name || event?.title || '');
        const eventDate = act ? (act.date_label || '') : (event?.date ? BileteOnlineUtils.formatDate(event.date, 'medium') : '');
        const eventTime = act ? (act.time_label || '') : this.eventTime(event);
        const place = act ? [act.venue, act.city].filter(Boolean).join(', ') : [this.venueName(event), event?.city].filter(Boolean).join(', ');
        const siteName = window.BILETEONLINE?.siteName || 'bilete.online';
        const seat = ticket.seat;
        const code = ticket.code || ticket.barcode || '';
        const attendee = ticket.attendee_name || this.order?.customer_name || 'Participant';
        const field = (label, value) => `<p><small>${label}</small><b>${this.esc(value)}</b></p>`;

        const seatFields = seat
            ? (seat.section_name ? field('Secțiune', seat.section_name) : '')
                + (seat.row_label ? field('Rând', seat.row_label) : '')
                + (seat.seat_number ? field('Loc', seat.seat_number) : '')
            : '';

        return `
            <article class="tk" data-index="${idx}" aria-label="Biletul ${idx + 1} din ${total}">
                <header class="tk-top">
                    <p><span>${this.esc(siteName)} · bilet</span><span>${idx + 1} / ${total}</span></p>
                    <h3>${this.esc(ticket.type || ticket.type_name || 'Bilet')}</h3>
                </header>
                <div class="tk-body">
                    <div class="tk-info">
                        ${eventTitle ? field(act ? 'Activitate' : 'Eveniment', eventTitle) : ''}
                        ${eventDate || eventTime ? `<div class="tk-pair">${eventDate ? field('Data', eventDate) : ''}${eventTime ? field('Ora', eventTime) : ''}</div>` : ''}
                        ${place ? field('Locație', place) : ''}
                        ${seatFields ? `<div class="tk-pair">${seatFields}</div>` : ''}
                        ${act && act.plate ? field('Mașina', act.plate) : ''}
                    </div>
                    <div class="tk-foot">
                        <div><small>Participant</small><b>${this.esc(attendee)}</b></div>
                        <div><small>Preț</small><b class="tk-price">${this.money(ticket.price)}</b></div>
                    </div>
                    ${ticket.has_insurance ? `<p class="tk-ins">${this.icon('check-circle')}Asigurat - Taxa de retur</p>` : ''}
                    <div class="tk-code">
                        <span class="tk-bars" aria-hidden="true">${bars}</span>
                        ${code ? `<p>${this.esc(code)}</p>` : ''}
                        ${ticket.ticket_series ? `<p>Serie: ${this.esc(ticket.ticket_series)}</p>` : ''}
                    </div>
                </div>
            </article>
        `;
    },

    copyLink() {
        const url = this.shareUrl();
        const done = () => this.notify('success', 'Link copiat în clipboard!');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done).catch(() => {
                window.prompt('Copiază linkul:', url);
            });
        } else {
            window.prompt('Copiază linkul:', url);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ThankYouPage.init());
