/**
 * Embed App — common JS for all embed pages.
 * Handles: iframe auto-resize, cart rendering, navigation.
 */
(function () {
    'use strict';

    const CONFIG = window.__EMBED_CONFIG__;
    if (!CONFIG) return;

    const CART_KEY = 'tixello_embed_cart_' + CONFIG.organizerSlug;

    // ========== Iframe Auto-Resize ==========

    let lastSentHeight = 0;

    function sendResize() {
        if (window.parent === window) return;
        const height = document.body.offsetHeight;
        // Only send if height changed by more than 5px (prevents infinite loop)
        if (Math.abs(height - lastSentHeight) > 5) {
            lastSentHeight = height;
            window.parent.postMessage({ type: 'tixello:resize', height: height + 20 }, '*');
        }
    }

    // Observe body size changes (debounced)
    let resizeTimer = null;
    if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(() => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(sendResize, 100);
        }).observe(document.body);
    }
    // Initial sends
    window.addEventListener('load', () => setTimeout(sendResize, 200));
    setTimeout(sendResize, 500);
    setTimeout(sendResize, 1500);

    // ========== Embed Cart ==========

    window.EmbedCart = {
        getCart() {
            const raw = localStorage.getItem(CART_KEY);
            return raw ? JSON.parse(raw) : { items: [] };
        },

        saveCart(cart) {
            localStorage.setItem(CART_KEY, JSON.stringify(cart));
            window.dispatchEvent(new CustomEvent('embed:cart:update', { detail: cart }));
        },

        addItem(eventData, ticketTypeData, quantity, meta) {
            const cart = this.getCart();
            const key = eventData.id + '_' + ticketTypeData.id + (meta?.visit_date ? '_' + meta.visit_date : '');

            const existing = cart.items.findIndex(i => i.key === key);
            if (existing >= 0) {
                cart.items[existing].quantity += quantity;
            } else {
                cart.items.push({
                    key,
                    event: eventData,
                    ticketType: ticketTypeData,
                    quantity,
                    meta: meta || null,
                    addedAt: new Date().toISOString(),
                });
            }
            this.saveCart(cart);
        },

        removeItem(key) {
            const cart = this.getCart();
            cart.items = cart.items.filter(i => i.key !== key);
            this.saveCart(cart);
        },

        updateQuantity(key, qty) {
            const cart = this.getCart();
            const item = cart.items.find(i => i.key === key);
            if (item) {
                if (qty <= 0) {
                    cart.items = cart.items.filter(i => i.key !== key);
                } else {
                    item.quantity = qty;
                }
                this.saveCart(cart);
            }
        },

        clearCart() {
            localStorage.removeItem(CART_KEY);
            window.dispatchEvent(new CustomEvent('embed:cart:update', { detail: { items: [] } }));
        },

        getItemCount() {
            return this.getCart().items.reduce((sum, i) => sum + i.quantity, 0);
        },

        getTotal() {
            return this.getCart().items.reduce((sum, i) => sum + (i.ticketType.price * i.quantity), 0);
        },

        getItemsForCheckout() {
            return this.getCart().items.map(item => ({
                event_id: item.event.id,
                ticket_type_id: item.ticketType.id,
                quantity: item.quantity,
                price: item.ticketType.price,
                ticket_type_name: item.ticketType.name,
                meta: item.meta || null,
                visit_date: item.meta?.visit_date || null,
                vehicle_info: item.meta?.vehicle_info || null,
            }));
        },
    };

    // ========== Cart Page Rendering ==========

    function renderCartPage() {
        const $empty = document.getElementById('embed-cart-empty');
        const $list = document.getElementById('embed-cart-items-list');
        const $footer = document.getElementById('embed-cart-footer');
        const $total = document.getElementById('embed-cart-page-total');

        if (!$empty || !$list) return; // Not on cart page

        const cart = EmbedCart.getCart();

        if (cart.items.length === 0) {
            $empty.style.display = '';
            $list.style.display = 'none';
            if ($footer) $footer.style.display = 'none';
            return;
        }

        $empty.style.display = 'none';
        $list.style.display = '';
        if ($footer) $footer.style.display = '';

        let html = '';
        cart.items.forEach(item => {
            const lineTotal = item.ticketType.price * item.quantity;
            html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:var(--card-bg, #fff);border:1px solid var(--border-color, #e2e8f0);border-radius:10px;margin-bottom:8px;">';
            html += '<div>';
            html += '<div style="font-weight:600;font-size:14px;">' + escHtml(item.ticketType.name) + '</div>';
            html += '<div style="font-size:12px;color:var(--muted-color, #64748b);">' + escHtml(item.event.title || item.event.name || '') + '</div>';
            if (item.meta?.visit_date) {
                html += '<div style="font-size:11px;color:var(--muted-color, #64748b);">Data: ' + item.meta.visit_date + '</div>';
            }
            html += '</div>';
            html += '<div style="display:flex;align-items:center;gap:12px;">';
            html += '<span style="font-size:14px;">' + item.quantity + '×</span>';
            html += '<span style="font-weight:600;">' + lineTotal.toFixed(2) + ' RON</span>';
            html += '<button onclick="EmbedCart.removeItem(\'' + item.key + '\');renderCartPage();" style="color:#ef4444;background:none;border:none;cursor:pointer;font-size:18px;" title="Elimină">×</button>';
            html += '</div></div>';
        });
        $list.innerHTML = html;

        if ($total) {
            $total.textContent = EmbedCart.getTotal().toFixed(2) + ' RON';
        }

        sendResize();
    }

    // Expose for cart page
    window.renderCartPage = renderCartPage;

    // Auto-render cart page if we're on it
    if (document.getElementById('embed-cart-container')) {
        renderCartPage();
    }

    function escHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

})();
