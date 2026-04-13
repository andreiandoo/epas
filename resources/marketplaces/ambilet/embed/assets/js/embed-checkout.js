/**
 * Embed Checkout — unified cart + checkout with commission, promo, qty edit.
 */
const EmbedCheckout = {
    promoCode: null,
    promoDiscount: 0,

    init() {
        const CONFIG = window.__EMBED_CONFIG__;
        if (!CONFIG || typeof EmbedCart === 'undefined') return;

        const cart = EmbedCart.getCart();
        if (cart.items.length === 0) {
            document.getElementById('emb-empty').style.display = '';
            document.getElementById('emb-checkout-wrap').style.display = 'none';
            return;
        }

        document.getElementById('emb-empty').style.display = 'none';
        document.getElementById('emb-checkout-wrap').style.display = '';
        this.renderItems();
        this.renderSummary();
    },

    renderItems() {
        const cart = EmbedCart.getCart();
        const $container = document.getElementById('emb-cart-items');
        if (!$container) return;

        let html = '';
        cart.items.forEach(item => {
            const price = parseFloat(item.ticketType.price || 0);
            const qty = item.quantity;
            const line = price * qty;

            html += '<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-color, #e2e8f0);">';

            // Event + ticket info
            html += '<div style="flex:1;min-width:0;">';
            html += '<div style="font-weight:600;font-size:14px;">' + this.esc(item.ticketType.name) + '</div>';
            html += '<div style="font-size:12px;color:var(--muted-color);">' + this.esc(item.event?.title || item.event?.name || '') + '</div>';
            if (item.meta?.visit_date) {
                html += '<div style="font-size:11px;color:var(--muted-color);">Data: ' + item.meta.visit_date + '</div>';
            }
            html += '</div>';

            // Qty controls
            html += '<div style="display:flex;align-items:center;gap:4px;">';
            html += '<button onclick="EmbedCheckout.changeQty(\'' + item.key + '\', -1)" style="width:26px;height:26px;border:1px solid var(--border-color);border-radius:6px;background:none;cursor:pointer;font-size:14px;line-height:1;">−</button>';
            html += '<span style="width:22px;text-align:center;font-weight:600;font-size:13px;">' + qty + '</span>';
            html += '<button onclick="EmbedCheckout.changeQty(\'' + item.key + '\', 1)" style="width:26px;height:26px;border:1px solid var(--border-color);border-radius:6px;background:none;cursor:pointer;font-size:14px;line-height:1;">+</button>';
            html += '</div>';

            // Line total
            html += '<div style="width:80px;text-align:right;font-weight:600;font-size:14px;">' + line.toFixed(2) + ' RON</div>';

            // Remove
            html += '<button onclick="EmbedCheckout.removeItem(\'' + item.key + '\')" style="color:#ef4444;background:none;border:none;cursor:pointer;font-size:16px;padding:4px;" title="Elimină">×</button>';

            html += '</div>';
        });

        $container.innerHTML = html;
    },

    renderSummary() {
        const cart = EmbedCart.getCart();
        const $lines = document.getElementById('emb-summary-lines');
        const $total = document.getElementById('emb-total');
        const $payBtn = document.getElementById('emb-pay-btn');
        const $discountLine = document.getElementById('emb-discount-line');

        let subtotal = 0;
        let html = '';

        cart.items.forEach(item => {
            const price = parseFloat(item.ticketType.price || 0);
            const line = price * item.quantity;
            subtotal += line;
            html += '<div style="display:flex;justify-content:space-between;margin-bottom:4px;">';
            html += '<span>' + item.quantity + '× ' + this.esc(item.ticketType.name) + '</span>';
            html += '<span style="font-weight:500;">' + line.toFixed(2) + '</span>';
            html += '</div>';
        });

        if ($lines) $lines.innerHTML = html;

        // Apply discount
        let total = subtotal;
        if (this.promoDiscount > 0) {
            total = Math.max(0, subtotal - this.promoDiscount);
            if ($discountLine) {
                $discountLine.style.display = 'flex';
                $discountLine.innerHTML = '<span>Reducere (' + this.esc(this.promoCode) + ')</span><span>-' + this.promoDiscount.toFixed(2) + ' RON</span>';
            }
        } else if ($discountLine) {
            $discountLine.style.display = 'none';
        }

        if ($total) $total.textContent = total.toFixed(2) + ' RON';
        if ($payBtn) $payBtn.disabled = cart.items.length === 0;
    },

    changeQty(key, dir) {
        const cart = EmbedCart.getCart();
        const item = cart.items.find(i => i.key === key);
        if (!item) return;

        let newQty = item.quantity + dir;
        const min = item.ticketType.min_per_order || 1;
        if (dir < 0 && newQty < min) newQty = 0; // Remove if below min
        if (newQty <= 0) {
            EmbedCart.removeItem(key);
        } else {
            EmbedCart.updateQuantity(key, newQty);
        }
        this.init(); // Re-render everything
    },

    removeItem(key) {
        EmbedCart.removeItem(key);
        this.init();
    },

    async applyPromo() {
        const code = document.getElementById('emb-promo')?.value.trim();
        const $msg = document.getElementById('emb-promo-msg');
        if (!code) return;

        $msg.style.display = '';
        $msg.style.color = 'var(--muted-color)';
        $msg.textContent = 'Se verifică...';

        try {
            const cart = EmbedCart.getCart();
            const eventId = cart.items[0]?.event?.id;
            const resp = await fetch(window.__EMBED_CONFIG__.siteUrl + '/api/proxy.php?action=promo.validate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code, event_id: eventId }),
                credentials: 'include',
            });
            const result = await resp.json();

            if (result.success && result.data?.valid) {
                this.promoCode = code;
                const type = result.data.type;
                const value = parseFloat(result.data.value || 0);
                const subtotal = EmbedCart.getTotal();

                if (type === 'percentage') {
                    this.promoDiscount = Math.round(subtotal * value) / 100;
                } else {
                    this.promoDiscount = value;
                }

                $msg.style.color = '#16a34a';
                $msg.textContent = 'Cod aplicat! Reducere: ' + this.promoDiscount.toFixed(2) + ' RON';
                this.renderSummary();
            } else {
                $msg.style.color = '#dc2626';
                $msg.textContent = result.data?.message || result.message || 'Cod invalid.';
                this.promoCode = null;
                this.promoDiscount = 0;
                this.renderSummary();
            }
        } catch (e) {
            $msg.style.color = '#dc2626';
            $msg.textContent = 'Eroare la verificare.';
        }
    },

    async submit() {
        const CONFIG = window.__EMBED_CONFIG__;
        const $error = document.getElementById('emb-error');
        const $btn = document.getElementById('emb-pay-btn');
        $error.style.display = 'none';
        $btn.disabled = true;
        $btn.textContent = 'Se procesează...';

        const cart = EmbedCart.getCart();
        if (cart.items.length === 0) {
            this.showError('Coșul este gol.');
            return;
        }

        // Validate form
        const firstName = document.getElementById('emb-first-name')?.value.trim();
        const lastName = document.getElementById('emb-last-name')?.value.trim();
        const email = document.getElementById('emb-email')?.value.trim();
        const phone = document.getElementById('emb-phone')?.value.trim();
        const terms = document.getElementById('emb-terms')?.checked;

        if (!firstName || !lastName || !email || !phone) {
            this.showError('Completează toate câmpurile obligatorii.');
            return;
        }
        if (!terms) {
            this.showError('Trebuie să accepți termenii și condițiile.');
            return;
        }

        try {
            // Build checkout items in format expected by API
            const items = cart.items.map(item => ({
                event: { id: item.event.id },
                ticketType: { id: item.ticketType.id, name: item.ticketType.name, price: item.ticketType.price },
                event_id: item.event.id,
                ticket_type_id: item.ticketType.id,
                quantity: item.quantity,
                price: item.ticketType.price,
                meta: item.meta || null,
            }));

            const checkoutData = {
                customer: { first_name: firstName, last_name: lastName, email, phone },
                items,
                payment_method: 'card',
                accept_terms: true,
            };

            if (this.promoCode) {
                checkoutData.promo_code = this.promoCode;
            }

            const response = await fetch(CONFIG.siteUrl + '/api/proxy.php?action=checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(checkoutData),
                credentials: 'include',
            });

            const result = await response.json();

            if (!result.success) {
                this.showError(result.message || result.error || 'Eroare la procesarea comenzii.');
                return;
            }

            // Extract order from response
            const order = result.data?.order || (result.data?.orders ? result.data.orders[0] : null);
            if (!order) {
                this.showError('Comanda nu a putut fi creată.');
                return;
            }

            // Initiate payment if needed
            if (parseFloat(order.total) > 0) {
                const thankYouUrl = CONFIG.returnUrl || (CONFIG.siteUrl + CONFIG.baseUrl + '/multumim?order=' + order.order_number);

                const payResp = await fetch(CONFIG.siteUrl + '/api/proxy.php?action=orders.pay&id=' + order.id, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        return_url: thankYouUrl,
                        cancel_url: window.location.href,
                    }),
                    credentials: 'include',
                });

                const payResult = await payResp.json();

                if (payResult.data?.payment_url) {
                    EmbedCart.clearCart();
                    // Break out of iframe for 3DS payment
                    if (window.parent !== window) {
                        window.top.location.href = payResult.data.payment_url;
                    } else {
                        window.location.href = payResult.data.payment_url;
                    }
                    return;
                }

                if (payResult.data?.form_data) {
                    EmbedCart.clearCart();
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = payResult.data.payment_url;
                    form.target = '_top';
                    for (const [key, value] of Object.entries(payResult.data.form_data)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }
                    document.body.appendChild(form);
                    form.submit();
                    return;
                }

                this.showError('Nu s-a putut iniția plata. Contactează organizatorul.');
                return;
            }

            // Free order
            EmbedCart.clearCart();
            window.location.href = CONFIG.baseUrl + '/multumim?order=' + order.order_number;

        } catch (err) {
            console.error('Checkout error:', err);
            this.showError('Eroare de rețea. Încearcă din nou.');
        }
    },

    showError(msg) {
        const $error = document.getElementById('emb-error');
        const $btn = document.getElementById('emb-pay-btn');
        $error.textContent = msg;
        $error.style.display = '';
        $btn.disabled = false;
        $btn.textContent = 'Plătește cu cardul';
    },

    esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => EmbedCheckout.init());
