/**
 * TICS.ro - Event Detail Page Controller
 * Handles ticket selection, countdown, tabs, cart interactions
 */

const TicsEventPage = {
    // State
    event: null,
    cart: {},
    totalPrice: 0,
    totalTickets: 0,
    isFavorite: false,

    /**
     * Initialize the page
     * @param {Object} eventData - Event data from PHP
     */
    init(eventData) {
        this.event = eventData;
        this.cart = {};

        // Initialize components
        this.initCountdown();
        this.initTabs();
        this.initTicketCards();
        this.loadCartFromStorage();
        this.updateCartBadge();
    },

    /**
     * Initialize countdown timer
     */
    initCountdown() {
        if (!this.event || !this.event.starts_at) return;

        const targetDate = new Date(this.event.starts_at).getTime();

        const updateCountdown = () => {
            const now = new Date().getTime();
            const diff = targetDate - now;

            if (diff <= 0) {
                document.getElementById('countDays').textContent = '00';
                document.getElementById('countHours').textContent = '00';
                document.getElementById('countMins').textContent = '00';
                document.getElementById('countSecs').textContent = '00';
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const secs = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('countDays').textContent = String(days).padStart(2, '0');
            document.getElementById('countHours').textContent = String(hours).padStart(2, '0');
            document.getElementById('countMins').textContent = String(mins).padStart(2, '0');
            document.getElementById('countSecs').textContent = String(secs).padStart(2, '0');
        };

        updateCountdown();
        setInterval(updateCountdown, 1000);
    },

    /**
     * Initialize tabs
     */
    initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                this.switchTab(tabName);
            });
        });
    },

    /**
     * Switch active tab
     */
    switchTab(tabName) {
        // Update button states
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
                btn.classList.remove('text-gray-500');
            } else {
                btn.classList.remove('active');
                btn.classList.add('text-gray-500');
            }
        });

        // Update content visibility
        document.querySelectorAll('.tab-content').forEach(content => {
            if (content.id === `tab-${tabName}`) {
                content.classList.remove('hidden');
            } else {
                content.classList.add('hidden');
            }
        });
    },

    /**
     * Initialize ticket card interactions
     */
    initTicketCards() {
        document.querySelectorAll('.ticket-card').forEach(card => {
            card.addEventListener('click', () => this.selectTicket(card));
        });
    },

    /**
     * Select a ticket card
     */
    selectTicket(card) {
        // Remove selection from other cards
        document.querySelectorAll('.ticket-card').forEach(c => {
            c.classList.remove('selected');
            c.classList.remove('border-gray-900');
            c.classList.add('border-gray-200');
        });

        // Select this card
        card.classList.add('selected');
        card.classList.add('border-gray-900');
        card.classList.remove('border-gray-200');
    },

    /**
     * Change ticket quantity
     */
    changeQty(ticketId, delta) {
        const qtyDisplay = document.querySelector(`.qty-display[data-ticket-id="${ticketId}"]`);
        if (!qtyDisplay) return;

        let qty = parseInt(qtyDisplay.textContent) || 0;
        qty = Math.max(0, Math.min(10, qty + delta)); // Min 0, Max 10
        qtyDisplay.textContent = qty;

        // Update cart state
        if (qty > 0) {
            const ticket = this.event.tickets.find(t => t.id === ticketId);
            if (ticket) {
                this.cart[ticketId] = {
                    name: ticket.name,
                    price: ticket.price,
                    qty: qty
                };
            }
        } else {
            delete this.cart[ticketId];
        }

        this.updateTotals();

        // Visual feedback
        qtyDisplay.classList.add('scale-125');
        setTimeout(() => qtyDisplay.classList.remove('scale-125'), 150);
    },

    /**
     * Update totals
     */
    updateTotals() {
        this.totalPrice = 0;
        this.totalTickets = 0;

        Object.values(this.cart).forEach(item => {
            this.totalPrice += item.price * item.qty;
            this.totalTickets += item.qty;
        });

        document.getElementById('totalPrice').textContent = this.totalPrice.toLocaleString('ro-RO') + ' RON';
        document.getElementById('totalTickets').textContent = this.totalTickets;

        // Enable/disable add to cart button
        const btn = document.getElementById('addToCartBtn');
        if (btn) {
            btn.disabled = this.totalTickets === 0;
        }
    },

    /**
     * Add to cart
     */
    addToCart() {
        if (this.totalTickets === 0) return;

        // Save to localStorage
        let globalCart = JSON.parse(localStorage.getItem('ticsCart') || '{}');
        const eventId = this.event.id;

        globalCart[eventId] = {
            eventName: this.event.name,
            eventSlug: this.event.slug,
            tickets: { ...this.cart }
        };

        localStorage.setItem('ticsCart', JSON.stringify(globalCart));

        // Show toast
        const firstItem = Object.values(this.cart)[0];
        this.showToast('Adăugat în coș!', `${this.totalTickets}x ${firstItem?.name || 'Bilete'}`);

        // Update cart badge
        this.updateCartBadge();

        // Button animation
        const btn = document.getElementById('addToCartBtn');
        if (btn) {
            btn.classList.add('animate-pulse');
            setTimeout(() => btn.classList.remove('animate-pulse'), 500);
        }
    },

    /**
     * Load cart from storage
     */
    loadCartFromStorage() {
        const globalCart = JSON.parse(localStorage.getItem('ticsCart') || '{}');
        const eventCart = globalCart[this.event?.id];

        if (eventCart && eventCart.tickets) {
            this.cart = eventCart.tickets;

            // Update UI
            Object.entries(this.cart).forEach(([ticketId, item]) => {
                const qtyDisplay = document.querySelector(`.qty-display[data-ticket-id="${ticketId}"]`);
                if (qtyDisplay) {
                    qtyDisplay.textContent = item.qty;
                }
            });

            this.updateTotals();
        }
    },

    /**
     * Update cart badge in header
     */
    updateCartBadge() {
        const badge = document.getElementById('cartBadge');
        if (!badge) return;

        const globalCart = JSON.parse(localStorage.getItem('ticsCart') || '{}');
        let totalItems = 0;

        Object.values(globalCart).forEach(event => {
            Object.values(event.tickets || {}).forEach(item => {
                totalItems += item.qty;
            });
        });

        badge.textContent = totalItems;
        badge.classList.toggle('hidden', totalItems === 0);
    },

    /**
     * Show toast notification
     */
    showToast(title, message) {
        const toast = document.getElementById('toast');
        const toastTitle = document.getElementById('toastTitle');
        const toastMessage = document.getElementById('toastMessage');

        if (toast && toastTitle && toastMessage) {
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.classList.remove('translate-x-full');

            setTimeout(() => this.hideToast(), 3000);
        }
    },

    /**
     * Hide toast notification
     */
    hideToast() {
        const toast = document.getElementById('toast');
        if (toast) {
            toast.classList.add('translate-x-full');
        }
    },

    /**
     * Toggle favorite
     */
    toggleFavorite() {
        this.isFavorite = !this.isFavorite;
        const icon = document.getElementById('favoriteIcon');

        if (icon) {
            if (this.isFavorite) {
                icon.classList.add('text-red-500');
                icon.setAttribute('fill', 'currentColor');
                this.showToast('Adăugat la favorite', this.event.name);
            } else {
                icon.classList.remove('text-red-500');
                icon.setAttribute('fill', 'none');
            }
        }

        // Save to localStorage
        let favorites = JSON.parse(localStorage.getItem('ticsFavorites') || '[]');
        if (this.isFavorite) {
            if (!favorites.includes(this.event.id)) {
                favorites.push(this.event.id);
            }
        } else {
            favorites = favorites.filter(id => id !== this.event.id);
        }
        localStorage.setItem('ticsFavorites', JSON.stringify(favorites));
    },

    /**
     * Share event
     */
    share() {
        if (navigator.share) {
            navigator.share({
                title: this.event.name,
                text: `Verifică acest eveniment pe TICS.ro: ${this.event.name}`,
                url: window.location.href
            });
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href).then(() => {
                this.showToast('Link copiat!', 'Poți împărtăși link-ul cu prietenii');
            });
        }
    },

    /**
     * Open cart drawer
     */
    openCart() {
        // For now, redirect to cart page
        window.location.href = '/cos';
    },

    /**
     * Set gallery image
     */
    setGalleryImage(index) {
        // Update thumbnail active state
        document.querySelectorAll('.gallery-thumb').forEach((thumb, i) => {
            if (i === index) {
                thumb.classList.add('ring-2', 'ring-white', 'ring-offset-2', 'ring-offset-gray-900');
                thumb.classList.remove('opacity-70');
            } else {
                thumb.classList.remove('ring-2', 'ring-white', 'ring-offset-2', 'ring-offset-gray-900');
                thumb.classList.add('opacity-70');
            }
        });

        // Could also update main hero image if implementing lightbox
    }
};

// Make available globally
window.TicsEventPage = TicsEventPage;
