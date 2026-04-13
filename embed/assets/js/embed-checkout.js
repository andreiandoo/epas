/**
 * Embed Checkout — handles order creation + payment redirect.
 */
(function () {
    'use strict';

    const CONFIG = window.__EMBED_CONFIG__;
    if (!CONFIG || typeof EmbedCart === 'undefined') return;

    const $form = document.getElementById('embed-checkout-form');
    const $items = document.getElementById('embed-checkout-items');
    const $total = document.getElementById('embed-checkout-total');
    const $error = document.getElementById('emb-checkout-error');
    const $submitBtn = document.getElementById('emb-submit-btn');

    // Render order summary
    function renderSummary() {
        const cart = EmbedCart.getCart();

        if (cart.items.length === 0) {
            window.location.href = CONFIG.baseUrl;
            return;
        }

        let html = '';
        let total = 0;

        cart.items.forEach(item => {
            const line = item.ticketType.price * item.quantity;
            total += line;
            html += '<div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">';
            html += '<span>' + item.quantity + '× ' + esc(item.ticketType.name) + '</span>';
            html += '<span style="font-weight:500;">' + line.toFixed(2) + ' RON</span>';
            html += '</div>';
        });

        if ($items) $items.innerHTML = html;
        if ($total) $total.textContent = total.toFixed(2) + ' RON';
    }

    renderSummary();

    // Handle form submit
    $form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        $error.style.display = 'none';
        $submitBtn.disabled = true;
        $submitBtn.textContent = 'Se procesează...';

        const cart = EmbedCart.getCart();
        if (cart.items.length === 0) {
            showError('Coșul este gol.');
            return;
        }

        const customer = {
            first_name: document.getElementById('emb-first-name').value.trim(),
            last_name: document.getElementById('emb-last-name').value.trim(),
            email: document.getElementById('emb-email').value.trim(),
            phone: document.getElementById('emb-phone').value.trim(),
        };

        if (!customer.first_name || !customer.last_name || !customer.email || !customer.phone) {
            showError('Completează toate câmpurile obligatorii.');
            return;
        }

        try {
            // Step 1: Create order
            const checkoutData = {
                customer,
                items: EmbedCart.getItemsForCheckout(),
                payment_method: 'card',
                accept_terms: true,
            };

            const response = await fetch(CONFIG.siteUrl + '/api/proxy.php?action=checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(checkoutData),
                credentials: 'include',
            });

            const result = await response.json();

            if (!result.success) {
                showError(result.message || 'Eroare la procesarea comenzii.');
                return;
            }

            const orders = result.data?.orders || [result.data?.order];
            const order = orders[0];

            if (!order) {
                showError('Comanda nu a putut fi creată.');
                return;
            }

            // Step 2: If payment required, initiate payment
            if (order.total > 0 && result.data?.payment_required !== false) {
                const thankYouUrl = CONFIG.returnUrl || (CONFIG.siteUrl + CONFIG.baseUrl + '/multumim?order=' + order.order_number);

                const payResponse = await fetch(CONFIG.siteUrl + '/api/proxy.php?action=orders.pay&id=' + order.id, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        return_url: thankYouUrl,
                        cancel_url: window.location.href,
                    }),
                    credentials: 'include',
                });

                const payResult = await payResponse.json();

                if (payResult.data?.payment_url) {
                    // Clear cart before redirect
                    EmbedCart.clearCart();

                    // For iframe: redirect to payment in same window (breaks out of iframe for 3DS)
                    if (window.parent !== window) {
                        window.top.location.href = payResult.data.payment_url;
                    } else {
                        window.location.href = payResult.data.payment_url;
                    }
                    return;
                }

                if (payResult.data?.form_data) {
                    // POST-based payment (Netopia)
                    EmbedCart.clearCart();
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = payResult.data.payment_url;
                    form.target = '_top'; // Break out of iframe
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

                showError('Nu s-a putut iniția plata.');
                return;
            }

            // Free order — go to thank you
            EmbedCart.clearCart();
            window.location.href = CONFIG.baseUrl + '/multumim?order=' + order.order_number;

        } catch (err) {
            console.error('Checkout error:', err);
            showError('Eroare de rețea. Încearcă din nou.');
        }
    });

    function showError(msg) {
        $error.textContent = msg;
        $error.style.display = '';
        $submitBtn.disabled = false;
        $submitBtn.textContent = 'Plătește cu cardul';
    }

    function esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

})();
