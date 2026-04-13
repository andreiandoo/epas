/**
 * WL Checkout — unified cart + checkout with commission, promo, qty edit.
 */
const WLCheckout = {
    promoCode: null,
    promoDiscount: 0,

    init() {
        const cart = WLCart.getCart();
        if (!cart.items.length) { document.getElementById('wl-empty').style.display = ''; return; }
        document.getElementById('wl-checkout-wrap').style.display = '';
        this.renderItems();
        this.renderSummary();
    },

    renderItems() {
        const cart = WLCart.getCart();
        const $c = document.getElementById('wl-ck-items'); if (!$c) return;
        let html = '';
        cart.items.forEach(item => {
            const p = parseFloat(item.ticketType.price || 0);
            html += '<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);">';
            html += '<div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:14px;">' + esc(item.ticketType.name) + '</div>';
            html += '<div style="font-size:12px;color:var(--muted);">' + esc(item.event?.title || '') + '</div>';
            if (item.meta?.visit_date) html += '<div style="font-size:11px;color:var(--muted);">Data: ' + item.meta.visit_date + '</div>';
            html += '</div>';
            html += '<div class="wl-qty"><button onclick="WLCheckout.changeQty(\'' + item.key + '\',-1)">−</button>';
            html += '<span>' + item.quantity + '</span>';
            html += '<button onclick="WLCheckout.changeQty(\'' + item.key + '\',1)">+</button></div>';
            html += '<div style="width:80px;text-align:right;font-weight:600;">' + (p * item.quantity).toFixed(2) + ' RON</div>';
            html += '<button onclick="WLCheckout.removeItem(\'' + item.key + '\')" style="color:#ef4444;background:none;border:none;cursor:pointer;font-size:16px;">×</button>';
            html += '</div>';
        });
        $c.innerHTML = html;
    },

    renderSummary() {
        const cart = WLCart.getCart();
        let subtotal = 0, html = '';
        cart.items.forEach(i => {
            const line = parseFloat(i.ticketType.price) * i.quantity;
            subtotal += line;
            html += '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>' + i.quantity + '× ' + esc(i.ticketType.name) + '</span><span>' + line.toFixed(2) + '</span></div>';
        });
        document.getElementById('wl-ck-summary').innerHTML = html;

        let total = subtotal;
        const $disc = document.getElementById('wl-ck-discount');
        if (this.promoDiscount > 0) {
            total = Math.max(0, subtotal - this.promoDiscount);
            $disc.style.display = 'flex';
            $disc.innerHTML = '<span>Reducere (' + esc(this.promoCode) + ')</span><span>-' + this.promoDiscount.toFixed(2) + ' RON</span>';
        } else { $disc.style.display = 'none'; }

        document.getElementById('wl-ck-total').textContent = total.toFixed(2) + ' RON';
        document.getElementById('wl-pay-btn').disabled = !cart.items.length;
    },

    changeQty(key, dir) {
        const cart = WLCart.getCart();
        const item = cart.items.find(i => i.key === key); if (!item) return;
        let q = item.quantity + dir;
        if (dir < 0 && q < (item.ticketType.min_per_order || 1)) q = 0;
        if (q <= 0) WLCart.removeItem(key); else WLCart.updateQuantity(key, q);
        this.init();
    },

    removeItem(key) { WLCart.removeItem(key); this.init(); },

    async applyPromo() {
        const code = document.getElementById('wl-promo')?.value.trim();
        const $msg = document.getElementById('wl-promo-msg');
        if (!code) return;
        $msg.style.display = ''; $msg.style.color = 'var(--muted)'; $msg.textContent = 'Se verifică...';

        try {
            const cart = WLCart.getCart();
            const resp = await WLApi.post('/promo-codes/validate', { code, event_id: cart.items[0]?.event?.id });
            if (resp.success && resp.data?.valid) {
                this.promoCode = code;
                const val = parseFloat(resp.data.value || 0);
                this.promoDiscount = resp.data.type === 'percentage' ? Math.round(WLCart.getTotal() * val) / 100 : val;
                $msg.style.color = '#16a34a'; $msg.textContent = 'Reducere: ' + this.promoDiscount.toFixed(2) + ' RON';
            } else {
                this.promoCode = null; this.promoDiscount = 0;
                $msg.style.color = '#dc2626'; $msg.textContent = resp.data?.message || 'Cod invalid.';
            }
            this.renderSummary();
        } catch (e) { $msg.style.color = '#dc2626'; $msg.textContent = 'Eroare.'; }
    },

    async submit() {
        const $err = document.getElementById('wl-ck-error');
        const $btn = document.getElementById('wl-pay-btn');
        $err.style.display = 'none'; $btn.disabled = true; $btn.textContent = 'Se procesează...';

        const fn = document.getElementById('wl-fn')?.value.trim();
        const ln = document.getElementById('wl-ln')?.value.trim();
        const em = document.getElementById('wl-em')?.value.trim();
        const ph = document.getElementById('wl-ph')?.value.trim();
        if (!fn || !ln || !em || !ph) { this.showError('Completează toate câmpurile.'); return; }
        if (!document.getElementById('wl-terms')?.checked) { this.showError('Acceptă termenii.'); return; }

        const cart = WLCart.getCart();
        const items = cart.items.map(i => ({
            event: { id: i.event.id }, ticketType: { id: i.ticketType.id, name: i.ticketType.name, price: i.ticketType.price },
            event_id: i.event.id, ticket_type_id: i.ticketType.id, quantity: i.quantity, price: i.ticketType.price, meta: i.meta,
        }));

        try {
            const data = { customer: { first_name: fn, last_name: ln, email: em, phone: ph }, items, payment_method: 'card', accept_terms: true };
            if (this.promoCode) data.promo_code = this.promoCode;

            const result = await WLApi.post('/customer/checkout', data);
            if (!result.success) { this.showError(result.message || result.error || 'Eroare.'); return; }

            const order = result.data?.order || result.data?.orders?.[0];
            if (!order) { this.showError('Comanda nu a fost creată.'); return; }

            if (parseFloat(order.total) > 0) {
                const returnUrl = window.location.origin + (typeof WL_BASE !== 'undefined' ? WL_BASE : '') + '/multumim?order=' + order.order_number;
                const payResult = await WLApi.post('/orders/' + order.id + '/pay', { return_url: returnUrl, cancel_url: window.location.href });

                if (payResult.data?.payment_url) {
                    WLCart.clearCart();
                    window.location.href = payResult.data.payment_url;
                    return;
                }
                if (payResult.data?.form_data) {
                    WLCart.clearCart();
                    const form = document.createElement('form'); form.method = 'POST'; form.action = payResult.data.payment_url;
                    for (const [k, v] of Object.entries(payResult.data.form_data)) { const inp = document.createElement('input'); inp.type = 'hidden'; inp.name = k; inp.value = v; form.appendChild(inp); }
                    document.body.appendChild(form); form.submit(); return;
                }
                this.showError('Plata nu a putut fi inițiată.'); return;
            }

            WLCart.clearCart();
            window.location.href = (typeof WL_BASE !== 'undefined' ? WL_BASE : '') + '/multumim?order=' + order.order_number;
        } catch (e) { console.error(e); this.showError('Eroare de rețea.'); }
    },

    showError(msg) {
        document.getElementById('wl-ck-error').textContent = msg;
        document.getElementById('wl-ck-error').style.display = '';
        document.getElementById('wl-pay-btn').disabled = false;
        document.getElementById('wl-pay-btn').textContent = 'Plătește cu cardul';
    }
};

document.addEventListener('DOMContentLoaded', () => WLCheckout.init());
