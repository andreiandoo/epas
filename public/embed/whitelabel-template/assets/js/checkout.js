/**
 * WL Checkout — premium cart + checkout with order summary.
 */
// Format price: 2 decimals if fractional, integer if whole
function fmtP(n) { return n % 1 !== 0 ? n.toFixed(2) : n.toFixed(0); }

var WLCheckout = {
    promoCode: null,
    promoDiscount: 0,
    // Organizer-level commission defaults (injected from checkout.php)
    orgCommRate: (window.__WL_COMMISSION__ && window.__WL_COMMISSION__.rate) || 5,

    init: function() {
        var cart = WLCart.getCart();
        if (!cart.items.length) {
            document.getElementById('wl-empty').style.display = '';
            document.getElementById('wl-checkout-wrap').style.display = 'none';
            return;
        }
        document.getElementById('wl-empty').style.display = 'none';
        document.getElementById('wl-checkout-wrap').style.display = '';
        this.renderCartBlocks();
        this.renderOrderLines();
    },

    renderCartBlocks: function() {
        var cart = WLCart.getCart();
        var $blocks = document.getElementById('wl-cart-blocks');
        var self = this;
        var html = '';

        // Group items by event
        var groups = {};
        cart.items.forEach(function(item) {
            var eid = item.event.id;
            if (!groups[eid]) groups[eid] = { event: item.event, tickets: [] };
            groups[eid].tickets.push(item);
        });

        Object.values(groups).forEach(function(group) {
            var ev = group.event;
            html += '<div class="event-block">';
            html += '<div class="event-block-header">';
            html += '<div class="event-poster">';
            if (ev.image) html += '<img src="' + esc(ev.image) + '" alt="">';
            else html += '<span style="font-family:var(--font-display);font-size:26px;font-style:italic;color:var(--accent);">♪</span>';
            html += '</div>';
            html += '<div class="event-info"><div class="event-info-name">' + esc(ev.title || ev.name || '') + '</div>';
            html += '<div class="event-info-meta">';
            if (ev.date) html += '<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' + ev.date + '</span>';
            html += '</div></div>';
            html += '<a href="' + (typeof WL_BASE !== 'undefined' ? WL_BASE : '') + '/' + esc(ev.slug || '') + '" class="edit-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>Modifică</a>';
            html += '</div>';

            group.tickets.forEach(function(item) {
                var price = parseFloat(item.ticketType.price || 0);
                var lineTotal = price * item.quantity;
                html += '<div class="ticket-row">';
                html += '<div class="ticket-row-left"><div class="ticket-row-type">' + esc(item.ticketType.name) + '</div></div>';
                html += '<div class="ticket-row-right">';
                html += '<div class="qty-control-sm">';
                html += '<button class="qty-btn-sm" onclick="WLCheckout.changeQty(\'' + item.key + '\',-1)">−</button>';
                html += '<div class="qty-val-sm">' + item.quantity + '</div>';
                html += '<button class="qty-btn-sm" onclick="WLCheckout.changeQty(\'' + item.key + '\',1)">+</button>';
                html += '</div>';
                html += '<div class="ticket-row-price">' + fmtP(lineTotal) + ' lei</div>';
                html += '<button class="ticket-row-remove" onclick="WLCheckout.removeItem(\'' + item.key + '\')" title="Elimină"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>';
                html += '</div></div>';
            });

            html += '</div>';
        });

        $blocks.innerHTML = html;
    },

    renderOrderLines: function() {
        var cart = WLCart.getCart();
        var $lines = document.getElementById('wl-order-lines');
        var $total = document.getElementById('wl-total');
        var $btn = document.getElementById('wl-pay-btn');
        var subtotalBase = 0;
        var totalComm = 0;
        var html = '';
        var self = this;
        var rate = self.orgCommRate;

        // Always compute commission for every item, regardless of mode
        cart.items.forEach(function(item) {
            var tt = item.ticketType;
            var displayPrice = parseFloat(tt.price || 0);
            var basePrice, commAmt;

            if (tt.base_price !== undefined && tt.base_price !== null) {
                // New cart format — pre-computed
                basePrice = parseFloat(tt.base_price);
                commAmt = parseFloat(tt.commission_amount || 0);
            } else {
                // Compute from org defaults: base = display / (1 + rate/100)
                // This works for both modes: on_top (display includes comm) and included (display IS the price, comm is inside)
                basePrice = Math.round(displayPrice / (1 + rate / 100) * 100) / 100;
                commAmt = Math.round((displayPrice - basePrice) * 100) / 100;
            }

            var lineBase = Math.round(basePrice * item.quantity * 100) / 100;
            var lineComm = Math.round(commAmt * item.quantity * 100) / 100;
            subtotalBase += lineBase;
            totalComm += lineComm;

            html += '<div class="order-line"><div class="order-line-label"><span class="order-line-qty">' + item.quantity + ' × </span>' + esc(tt.name) + '</div><div class="order-line-amount">' + fmtP(lineBase) + ' lei</div></div>';
        });

        // Always show commission line
        if (totalComm > 0) {
            html += '<div class="order-line"><div class="order-line-label" style="color:var(--text-muted);font-size:12px;">Comisioane</div><div class="order-line-amount" style="color:var(--text-muted);font-size:12px;">' + fmtP(totalComm) + ' lei</div></div>';
        }

        var grandTotal = subtotalBase + totalComm;

        if (this.promoDiscount > 0) {
            grandTotal = Math.max(0, grandTotal - this.promoDiscount);
            html += '<div class="order-line"><div class="order-line-label" style="color:#5cc87a;">Reducere (' + esc(this.promoCode) + ')</div><div class="order-line-amount" style="color:#5cc87a;">-' + this.promoDiscount.toFixed(0) + ' lei</div></div>';
        }

        $lines.innerHTML = html;
        $total.textContent = fmtP(grandTotal);
        $btn.disabled = !cart.items.length;
    },

    changeQty: function(key, dir) {
        var cart = WLCart.getCart();
        var item = cart.items.find(function(i) { return i.key === key; });
        if (!item) return;
        var q = item.quantity + dir;
        if (dir < 0 && q < (item.ticketType.min_per_order || 1)) q = 0;
        if (q <= 0) WLCart.removeItem(key); else WLCart.updateQuantity(key, q);
        this.init();
    },

    removeItem: function(key) { WLCart.removeItem(key); this.init(); },

    applyPromo: function() {
        var code = document.getElementById('wl-promo').value.trim();
        var $msg = document.getElementById('wl-promo-msg');
        if (!code) return;
        $msg.style.display = 'block';
        $msg.style.color = 'var(--text-muted)';
        $msg.textContent = 'Se verifică...';
        // TODO: API promo validation
        var self = this;
        setTimeout(function() {
            $msg.style.color = 'var(--text-dim)';
            $msg.textContent = 'Funcționalitate în curs de implementare.';
        }, 500);
    },

    submit: function() {
        var $err = document.getElementById('wl-error');
        var $btn = document.getElementById('wl-pay-btn');
        $err.style.display = 'none';
        $btn.disabled = true;
        $btn.querySelector('svg + *') || ($btn.textContent = 'Se procesează...');

        var fn = document.getElementById('wl-fn').value.trim();
        var ln = document.getElementById('wl-ln').value.trim();
        var em = document.getElementById('wl-em').value.trim();
        var ph = document.getElementById('wl-ph').value.trim();
        var termsChecked = document.getElementById('wl-terms-check').classList.contains('checked');

        if (!fn || !ln || !em || !ph) { this.showError('Completează toate câmpurile obligatorii.'); return; }
        if (!termsChecked) { this.showError('Trebuie să accepți termenii și condițiile.'); return; }

        var cart = WLCart.getCart();
        var items = cart.items.map(function(i) {
            return {
                event: { id: i.event.id },
                ticketType: { id: i.ticketType.id, name: i.ticketType.name, price: i.ticketType.price },
                event_id: i.event.id, ticket_type_id: i.ticketType.id,
                quantity: i.quantity, price: i.ticketType.price, meta: i.meta
            };
        });

        var data = {
            customer: { first_name: fn, last_name: ln, email: em, phone: ph },
            items: items, payment_method: 'card', accept_terms: true
        };
        if (this.promoCode) data.promo_code = this.promoCode;

        var self = this;
        WLApi.post('/customer/checkout', data).then(function(result) {
            if (!result.success) { self.showError(result.message || result.error || 'Eroare.'); return; }

            var order = result.data && (result.data.order || (result.data.orders && result.data.orders[0]));
            if (!order) { self.showError('Comanda nu a fost creată.'); return; }

            // Redirect to marketplace for payment
            WLCart.clearCart();
            var base = typeof WL_BASE !== 'undefined' ? WL_BASE : '';
            var siteOrigin = window.location.origin + base;
            var returnUrl = encodeURIComponent(siteOrigin + '/multumim?order=' + order.order_number);
            var cancelUrl = encodeURIComponent(siteOrigin + '/checkout');
            var marketplaceUrl = '{{MARKETPLACE_URL}}';
            window.location.href = marketplaceUrl + '/plata/' + order.order_number + '?order_id=' + order.id + '&return_url=' + returnUrl + '&cancel_url=' + cancelUrl;
        }).catch(function(err) {
            console.error(err);
            self.showError('Eroare de rețea. Încearcă din nou.');
        });
    },

    showError: function(msg) {
        var $err = document.getElementById('wl-error');
        var $btn = document.getElementById('wl-pay-btn');
        $err.textContent = msg;
        $err.style.display = '';
        $btn.disabled = false;
        $btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Finalizează comanda';
    }
};

document.addEventListener('DOMContentLoaded', function() { WLCheckout.init(); });
