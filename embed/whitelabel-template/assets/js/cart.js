/**
 * WLCart — localStorage cart manager for whitelabel sites.
 */
const WLCart = {
    KEY: 'wl_cart',

    getCart() {
        const raw = localStorage.getItem(this.KEY);
        return raw ? JSON.parse(raw) : { items: [] };
    },

    saveCart(cart) {
        localStorage.setItem(this.KEY, JSON.stringify(cart));
        window.dispatchEvent(new CustomEvent('wl:cart:update', { detail: cart }));
    },

    addItem(eventData, ticketTypeData, quantity, meta) {
        const cart = this.getCart();
        const key = eventData.id + '_' + ticketTypeData.id + (meta?.visit_date ? '_' + meta.visit_date : '');
        const idx = cart.items.findIndex(i => i.key === key);

        if (idx >= 0) {
            cart.items[idx].quantity += quantity;
        } else {
            cart.items.push({
                key,
                event: { id: eventData.id, title: eventData.title || eventData.name, slug: eventData.slug, image: eventData.image },
                ticketType: { id: ticketTypeData.id, name: ticketTypeData.name, price: ticketTypeData.price, commission: ticketTypeData.commission, min_per_order: ticketTypeData.min_per_order || 1, max_per_order: ticketTypeData.max_per_order || 10 },
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
            if (qty <= 0) cart.items = cart.items.filter(i => i.key !== key);
            else item.quantity = qty;
            this.saveCart(cart);
        }
    },

    clearCart() {
        localStorage.removeItem(this.KEY);
        window.dispatchEvent(new CustomEvent('wl:cart:update', { detail: { items: [] } }));
    },

    getItemCount() {
        return this.getCart().items.reduce((s, i) => s + i.quantity, 0);
    },

    getTotal() {
        return this.getCart().items.reduce((s, i) => s + (parseFloat(i.ticketType.price) * i.quantity), 0);
    },
};
