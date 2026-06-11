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
    isFollowing: false,
    discount: 0,
    currentSlide: 0,
    slideInterval: null,
    totalSlides: 0,

    /**
     * Initialize the page
     * @param {Object} eventData - Event data from PHP
     */
    init(eventData) {
        this.event = eventData;
        this.cart = {};

        // Initialize components
        this.initCarousel();
        this.initCountdown();
        this.initTabs();
        this.initTicketCards();
        this.loadCartFromStorage();
        this.loadFavoriteStatus();
        this.loadFollowStatus();
        this.updateCartBadge();
    },

    /**
     * Initialize hero image carousel
     */
    initCarousel() {
        const slides = document.querySelectorAll('.hero-slide');
        this.totalSlides = slides.length;

        if (this.totalSlides <= 1) return;

        // Auto-play carousel
        this.slideInterval = setInterval(() => this.nextSlide(), 5000);

        // Pause on hover
        const heroSection = document.querySelector('.hero-slide')?.closest('.relative');
        if (heroSection) {
            heroSection.addEventListener('mouseenter', () => {
                if (this.slideInterval) clearInterval(this.slideInterval);
            });
            heroSection.addEventListener('mouseleave', () => {
                this.slideInterval = setInterval(() => this.nextSlide(), 5000);
            });
        }
    },

    /**
     * Go to specific slide
     */
    goToSlide(index) {
        if (index < 0 || index >= this.totalSlides) return;

        this.currentSlide = index;

        // Update slides
        document.querySelectorAll('.hero-slide').forEach((slide, i) => {
            if (i === index) {
                slide.classList.remove('opacity-0');
                slide.classList.add('opacity-100');
            } else {
                slide.classList.remove('opacity-100');
                slide.classList.add('opacity-0');
            }
        });

        // Update indicators
        document.querySelectorAll('.hero-indicator').forEach((indicator, i) => {
            if (i === index) {
                indicator.classList.remove('bg-white/50', 'hover:bg-white/70', 'w-2');
                indicator.classList.add('bg-white', 'w-6');
            } else {
                indicator.classList.remove('bg-white', 'w-6');
                indicator.classList.add('bg-white/50', 'hover:bg-white/70', 'w-2');
            }
        });

        // Also update gallery thumbnails
        this.setGalleryImage(index);

        // Reset auto-play timer
        if (this.slideInterval) {
            clearInterval(this.slideInterval);
            this.slideInterval = setInterval(() => this.nextSlide(), 5000);
        }
    },

    /**
     * Go to next slide
     */
    nextSlide() {
        const next = (this.currentSlide + 1) % this.totalSlides;
        this.goToSlide(next);
    },

    /**
     * Go to previous slide
     */
    prevSlide() {
        const prev = (this.currentSlide - 1 + this.totalSlides) % this.totalSlides;
        this.goToSlide(prev);
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

        // Initialize tooltip positioning
        this.initTicketTooltips();
    },

    /**
     * Initialize ticket tooltip positioning based on name width
     */
    initTicketTooltips() {
        document.querySelectorAll('.ticket-card').forEach(card => {
            const ticketName = card.querySelector('.ticket-name');
            const tooltip = card.querySelector('.ticket-tooltip');
            const tooltipContainer = card.querySelector('.ticket-tooltip-container');
            const tooltipArrow = card.querySelector('.ticket-tooltip-arrow');

            if (!ticketName || !tooltip || !tooltipContainer) return;

            // Get the card width and ticket name width
            const cardRect = card.getBoundingClientRect();
            const nameRect = ticketName.getBoundingClientRect();

            // Calculate if name exceeds half the card width
            const nameExceedsHalf = nameRect.width > cardRect.width / 2;

            // Position tooltip: if name is long, show to the left; otherwise to the right
            if (nameExceedsHalf) {
                tooltip.classList.add('tooltip-left');
                tooltip.classList.remove('tooltip-right');
                tooltip.style.left = 'auto';
                tooltip.style.right = '0';
                if (tooltipArrow) {
                    tooltipArrow.style.right = '4px';
                    tooltipArrow.style.left = 'auto';
                }
            } else {
                tooltip.classList.add('tooltip-right');
                tooltip.classList.remove('tooltip-left');
                tooltip.style.left = '0';
                tooltip.style.right = 'auto';
                if (tooltipArrow) {
                    tooltipArrow.style.left = '4px';
                    tooltipArrow.style.right = 'auto';
                }
            }
        });

        // Recalculate on window resize
        window.addEventListener('resize', TicsUtils.debounce(() => {
            this.initTicketTooltips();
        }, 200));
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
                    base_price: ticket.base_price,
                    service_fee: ticket.service_fee,
                    platform_fee: ticket.platform_fee,
                    fees_included: ticket.fees_included,
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
     * Update totals with tax breakdown
     */
    updateTotals() {
        this.totalPrice = 0;
        this.totalTickets = 0;
        let baseSubtotal = 0;
        let serviceFees = 0;
        let platformFees = 0;
        let hasExternalFees = false;

        Object.values(this.cart).forEach(item => {
            this.totalPrice += item.price * item.qty;
            this.totalTickets += item.qty;
            baseSubtotal += item.base_price * item.qty;
            serviceFees += item.service_fee * item.qty;
            platformFees += item.platform_fee * item.qty;
            if (!item.fees_included) {
                hasExternalFees = true;
            }
        });

        // Apply discount if any
        const finalPrice = Math.max(0, this.totalPrice - this.discount);

        // Update UI
        document.getElementById('totalPrice').textContent = finalPrice.toLocaleString('ro-RO') + ' RON';
        document.getElementById('totalTickets').textContent = this.totalTickets;

        // Show/hide tax breakdown if fees are external
        const taxBreakdown = document.getElementById('taxBreakdown');
        if (taxBreakdown) {
            if (hasExternalFees && this.totalTickets > 0) {
                taxBreakdown.classList.remove('hidden');
                document.getElementById('baseSubtotal').textContent = baseSubtotal.toLocaleString('ro-RO') + ' RON';
                document.getElementById('serviceFees').textContent = serviceFees.toLocaleString('ro-RO') + ' RON';
                document.getElementById('platformFees').textContent = platformFees.toLocaleString('ro-RO') + ' RON';
            } else {
                taxBreakdown.classList.add('hidden');
            }
        }

        // Show/hide points section
        const pointsSection = document.getElementById('pointsSection');
        if (pointsSection) {
            if (this.totalTickets > 0) {
                pointsSection.style.display = 'block';
                const earnPoints = Math.floor(this.totalPrice * 0.1); // 10% as points
                document.getElementById('earnPoints').textContent = earnPoints;
            } else {
                pointsSection.style.display = 'none';
            }
        }

        // Show/hide installment section
        const installmentSection = document.getElementById('installmentSection');
        if (installmentSection) {
            if (this.totalTickets > 0 && finalPrice >= 200) {
                installmentSection.style.display = 'block';
                const monthlyPrice = Math.ceil(finalPrice / 6);
                document.getElementById('monthlyPrice').textContent = monthlyPrice.toLocaleString('ro-RO') + ' RON';
            } else {
                installmentSection.style.display = 'none';
            }
        }

        // Enable/disable add to cart button
        const btn = document.getElementById('addToCartBtn');
        if (btn) {
            btn.disabled = this.totalTickets === 0;
        }
    },

    /**
     * Apply promo code
     */
    applyPromo() {
        const promoInput = document.getElementById('promoCode');
        const code = promoInput?.value.trim().toUpperCase();

        if (!code) {
            this.showToast('Eroare', 'Introdu un cod promoțional');
            return;
        }

        // Demo promo codes
        const promoCodes = {
            'WELCOME10': { type: 'percent', value: 10 },
            'VIP50': { type: 'fixed', value: 50 },
            'SUMMER20': { type: 'percent', value: 20 }
        };

        const promo = promoCodes[code];
        if (promo) {
            if (promo.type === 'percent') {
                this.discount = Math.floor(this.totalPrice * (promo.value / 100));
            } else {
                this.discount = promo.value;
            }

            // Show discount row
            const discountRow = document.getElementById('discountRow');
            if (discountRow) {
                discountRow.style.display = 'flex';
                document.getElementById('discountAmount').textContent = '-' + this.discount.toLocaleString('ro-RO') + ' RON';
            }

            this.updateTotals();
            this.showToast('Cod aplicat!', `Ai primit o reducere de ${this.discount} RON`);
            promoInput.value = '';
        } else {
            this.showToast('Cod invalid', 'Verifică codul și încearcă din nou');
        }
    },

    /**
     * Use loyalty points
     */
    usePoints() {
        this.discount = 50; // 500 points = 50 RON

        const discountRow = document.getElementById('discountRow');
        if (discountRow) {
            discountRow.style.display = 'flex';
            document.getElementById('discountAmount').textContent = '-50 RON';
        }

        this.updateTotals();
        this.showToast('Puncte folosite!', 'Ai folosit 500 puncte pentru o reducere de 50 RON');
    },

    /**
     * Add to cart and redirect to cart page
     */
    addToCart() {
        if (this.totalTickets === 0) return;

        // Build cart items
        const items = [];
        Object.entries(this.cart).forEach(([ticketId, item]) => {
            items.push({
                id: `${this.event.id}-${ticketId}`,
                eventId: this.event.id,
                eventName: this.event.name,
                eventSlug: this.event.slug,
                eventImage: this.event.image,
                venue: this.event.venue,
                date: this.event.starts_at,
                ticketId: parseInt(ticketId),
                ticketName: item.name,
                price: item.price,
                quantity: item.qty
            });
        });

        // Save to localStorage using cart format from cart.php
        let existingCart = JSON.parse(localStorage.getItem('tics_cart') || '[]');

        // Remove existing items for this event to avoid duplicates
        existingCart = existingCart.filter(item => item.eventId !== this.event.id);

        // Add new items
        existingCart = existingCart.concat(items);

        localStorage.setItem('tics_cart', JSON.stringify(existingCart));

        // Show toast
        const firstItem = Object.values(this.cart)[0];
        this.showToast('Adăugat în coș!', `${this.totalTickets}x ${firstItem?.name || 'Bilete'}`);

        // Update cart badge
        this.updateCartBadge();

        // Button animation
        const btn = document.getElementById('addToCartBtn');
        if (btn) {
            btn.classList.add('animate-pulse');
        }

        // Redirect to cart page after short delay
        if (this.event.redirectToCart) {
            setTimeout(() => {
                window.location.href = '/cos';
            }, 800);
        }
    },

    /**
     * Load cart from storage
     */
    loadCartFromStorage() {
        const existingCart = JSON.parse(localStorage.getItem('tics_cart') || '[]');
        const eventItems = existingCart.filter(item => item.eventId === this.event?.id);

        if (eventItems.length > 0) {
            eventItems.forEach(item => {
                const ticket = this.event.tickets.find(t => t.id === item.ticketId);
                if (ticket) {
                    this.cart[item.ticketId] = {
                        name: item.ticketName,
                        price: ticket.price,
                        base_price: ticket.base_price,
                        service_fee: ticket.service_fee,
                        platform_fee: ticket.platform_fee,
                        fees_included: ticket.fees_included,
                        qty: item.quantity
                    };

                    // Update UI
                    const qtyDisplay = document.querySelector(`.qty-display[data-ticket-id="${item.ticketId}"]`);
                    if (qtyDisplay) {
                        qtyDisplay.textContent = item.quantity;
                    }
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

        const existingCart = JSON.parse(localStorage.getItem('tics_cart') || '[]');
        const totalItems = existingCart.reduce((sum, item) => sum + item.quantity, 0);

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
     * Load favorite status
     */
    loadFavoriteStatus() {
        const favorites = JSON.parse(localStorage.getItem('ticsFavorites') || '[]');
        this.isFavorite = favorites.includes(this.event?.id);

        if (this.isFavorite) {
            const icon = document.getElementById('favoriteIcon');
            if (icon) {
                icon.classList.add('text-red-500');
                icon.setAttribute('fill', 'currentColor');
            }
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
     * Load follow status
     */
    loadFollowStatus() {
        const following = JSON.parse(localStorage.getItem('ticsFollowing') || '[]');
        this.isFollowing = following.includes(this.event?.id);

        if (this.isFollowing) {
            const btn = document.getElementById('followBtn');
            if (btn) {
                btn.textContent = 'Urmărești ✓';
                btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                btn.classList.add('bg-gray-200', 'text-gray-700');
            }
        }
    },

    /**
     * Toggle follow
     */
    toggleFollow() {
        this.isFollowing = !this.isFollowing;
        const btn = document.getElementById('followBtn');

        if (btn) {
            if (this.isFollowing) {
                btn.textContent = 'Urmărești ✓';
                btn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                btn.classList.add('bg-gray-200', 'text-gray-700');
                this.showToast('Urmărești evenimentul', 'Vei primi notificări despre noutăți');
            } else {
                btn.textContent = 'Urmărește';
                btn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
                btn.classList.remove('bg-gray-200', 'text-gray-700');
            }
        }

        // Save to localStorage
        let following = JSON.parse(localStorage.getItem('ticsFollowing') || '[]');
        if (this.isFollowing) {
            if (!following.includes(this.event.id)) {
                following.push(this.event.id);
            }
        } else {
            following = following.filter(id => id !== this.event.id);
        }
        localStorage.setItem('ticsFollowing', JSON.stringify(following));
    },

    /**
     * Share event using Web Share API
     */
    share() {
        if (navigator.share) {
            navigator.share({
                title: this.event.name,
                text: `Verifică acest eveniment pe TICS.ro: ${this.event.name}`,
                url: window.location.href
            });
        } else {
            this.copyLink();
        }
    },

    /**
     * Share to Facebook
     */
    shareToFacebook() {
        const url = encodeURIComponent(window.location.href);
        window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank', 'width=600,height=400');
    },

    /**
     * Share to X (Twitter)
     */
    shareToX() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(`Verifică acest eveniment: ${this.event.name}`);
        window.open(`https://twitter.com/intent/tweet?url=${url}&text=${text}`, '_blank', 'width=600,height=400');
    },

    /**
     * Share to WhatsApp
     */
    shareToWhatsApp() {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(`Verifică acest eveniment pe TICS.ro: ${this.event.name} - `);
        window.open(`https://wa.me/?text=${text}${url}`, '_blank');
    },

    /**
     * Copy link to clipboard
     */
    copyLink() {
        navigator.clipboard.writeText(window.location.href).then(() => {
            this.showToast('Link copiat!', 'Poți împărtăși link-ul cu prietenii');
        });
    },

    /**
     * Open cart drawer (redirect to cart page)
     */
    openCart() {
        window.location.href = '/cos';
    },

    // Directions Modal State
    transportMode: 'transit',
    venueCoords: null,

    /**
     * Open directions modal
     */
    openDirectionsModal() {
        const modal = document.getElementById('directionsModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            // Store venue coordinates from event data
            if (this.event && this.event.venue && typeof this.event.venue === 'object') {
                this.venueCoords = {
                    lat: this.event.venue.lat || 44.4378,
                    lng: this.event.venue.lng || 26.1546
                };
            } else {
                this.venueCoords = { lat: 44.4378, lng: 26.1546 }; // Default to Arena Nationala
            }
        }
    },

    /**
     * Close directions modal
     */
    closeDirectionsModal() {
        const modal = document.getElementById('directionsModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    },

    /**
     * Set transport mode
     */
    setTransportMode(mode) {
        this.transportMode = mode;

        // Update button states
        document.querySelectorAll('.transport-mode-btn').forEach(btn => {
            const btnMode = btn.dataset.mode;
            if (btnMode === mode) {
                btn.classList.add('active', 'bg-indigo-50', 'border-indigo-500');
                btn.classList.remove('bg-gray-50', 'border-gray-200');
                btn.querySelector('svg').classList.add('text-indigo-600');
                btn.querySelector('svg').classList.remove('text-gray-600');
                btn.querySelector('span').classList.add('text-indigo-700');
                btn.querySelector('span').classList.remove('text-gray-600');
            } else {
                btn.classList.remove('active', 'bg-indigo-50', 'border-indigo-500');
                btn.classList.add('bg-gray-50', 'border-gray-200');
                btn.querySelector('svg').classList.remove('text-indigo-600');
                btn.querySelector('svg').classList.add('text-gray-600');
                btn.querySelector('span').classList.remove('text-indigo-700');
                btn.querySelector('span').classList.add('text-gray-600');
            }
        });
    },

    /**
     * Use current location for origin
     */
    useCurrentLocation() {
        const statusEl = document.getElementById('locationStatus');
        const originInput = document.getElementById('directionsOrigin');

        if (!navigator.geolocation) {
            if (statusEl) {
                statusEl.textContent = 'Geolocalizarea nu este suportată de browser.';
                statusEl.classList.remove('hidden', 'text-green-600');
                statusEl.classList.add('text-red-600');
            }
            return;
        }

        if (statusEl) {
            statusEl.textContent = 'Se detectează locația...';
            statusEl.classList.remove('hidden', 'text-red-600', 'text-green-600');
            statusEl.classList.add('text-gray-400');
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                // Use reverse geocoding to get address
                this.reverseGeocode(lat, lng, (address) => {
                    if (originInput) {
                        originInput.value = address || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                    }
                    if (statusEl) {
                        statusEl.textContent = '✓ Locație detectată';
                        statusEl.classList.remove('text-gray-400', 'text-red-600');
                        statusEl.classList.add('text-green-600');
                    }
                });
            },
            (error) => {
                let message = 'Nu s-a putut detecta locația.';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'Accesul la locație a fost refuzat.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = 'Locația nu este disponibilă.';
                        break;
                    case error.TIMEOUT:
                        message = 'Cererea de locație a expirat.';
                        break;
                }
                if (statusEl) {
                    statusEl.textContent = message;
                    statusEl.classList.remove('hidden', 'text-gray-400', 'text-green-600');
                    statusEl.classList.add('text-red-600');
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    },

    /**
     * Reverse geocode coordinates to address
     */
    reverseGeocode(lat, lng, callback) {
        // Using Nominatim (OpenStreetMap) for reverse geocoding - free and no API key needed
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=ro`)
            .then(response => response.json())
            .then(data => {
                if (data && data.display_name) {
                    callback(data.display_name);
                } else {
                    callback(null);
                }
            })
            .catch(() => {
                callback(null);
            });
    },

    /**
     * Search for directions
     */
    searchDirections() {
        const originInput = document.getElementById('directionsOrigin');
        const origin = originInput?.value.trim();

        if (!origin) {
            this.showToast('Eroare', 'Introdu adresa de plecare');
            originInput?.focus();
            return;
        }

        const loadingEl = document.getElementById('directionsLoading');
        const resultsEl = document.getElementById('directionsResults');
        const errorEl = document.getElementById('directionsError');
        const routesList = document.getElementById('directionsRoutesList');

        // Show loading
        loadingEl?.classList.remove('hidden');
        resultsEl?.classList.add('hidden');
        errorEl?.classList.add('hidden');

        // Build destination
        let destination = 'Arena Națională, București';
        if (this.event?.venue) {
            if (typeof this.event.venue === 'object') {
                destination = `${this.event.venue.name}, ${this.event.venue.address}, ${this.event.venue.city}`;
            } else if (typeof this.event.venue === 'string') {
                destination = this.event.venue;
            }
        }

        // For Romania public transport, we'll use a combination of services
        // Since Google Maps Directions API requires billing, we'll create links to various services
        setTimeout(() => {
            loadingEl?.classList.add('hidden');

            // Generate route options based on transport mode
            const routes = this.generateRouteOptions(origin, destination);

            if (routesList) {
                routesList.innerHTML = routes.map(route => `
                    <a href="${route.url}" target="_blank" class="block p-4 bg-white border border-gray-200 rounded-xl hover:shadow-md hover:border-indigo-200 transition-all">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 ${route.bgColor} rounded-xl flex items-center justify-center flex-shrink-0">
                                ${route.icon}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">${route.name}</p>
                                <p class="text-sm text-gray-500">${route.description}</p>
                            </div>
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </div>
                    </a>
                `).join('');
            }

            resultsEl?.classList.remove('hidden');
        }, 500);
    },

    /**
     * Generate route options for different services
     */
    generateRouteOptions(origin, destination) {
        const encodedOrigin = encodeURIComponent(origin);
        const encodedDest = encodeURIComponent(destination);

        const routes = [];

        // Google Maps (works for all modes)
        const gmapsMode = this.transportMode === 'transit' ? 'r' :
            this.transportMode === 'driving' ? 'd' :
            this.transportMode === 'walking' ? 'w' : 'b';

        routes.push({
            name: 'Google Maps',
            description: this.getTransportModeLabel(this.transportMode),
            url: `https://www.google.com/maps/dir/?api=1&origin=${encodedOrigin}&destination=${encodedDest}&travelmode=${this.transportMode}`,
            icon: '<svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>',
            bgColor: 'bg-blue-500'
        });

        // Add mode-specific options
        if (this.transportMode === 'transit') {
            // Moovit (great for public transport in Romania)
            routes.push({
                name: 'Moovit',
                description: 'Transport public detaliat',
                url: `https://moovit.com/directions/${encodedOrigin}/${encodedDest}`,
                icon: '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>',
                bgColor: 'bg-green-500'
            });

            // STB București (if destination is in Bucharest)
            if (destination.toLowerCase().includes('bucurești') || destination.toLowerCase().includes('bucharest')) {
                routes.push({
                    name: 'InfoTrafic STB',
                    description: 'Autobuz, tramvai, metrou',
                    url: 'https://infotrafic.stbsa.ro/',
                    icon: '<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>',
                    bgColor: 'bg-red-500'
                });
            }
        } else if (this.transportMode === 'driving') {
            // Waze
            routes.push({
                name: 'Waze',
                description: 'Navigație cu trafic live',
                url: `https://waze.com/ul?ll=${this.venueCoords?.lat || 44.4378},${this.venueCoords?.lng || 26.1546}&navigate=yes`,
                icon: '<svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>',
                bgColor: 'bg-cyan-500'
            });

            // Apple Maps (if on iOS)
            routes.push({
                name: 'Apple Maps',
                description: 'Navigație Apple',
                url: `https://maps.apple.com/?daddr=${encodedDest}&saddr=${encodedOrigin}`,
                icon: '<svg class="w-6 h-6 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>',
                bgColor: 'bg-gray-700'
            });
        }

        return routes;
    },

    /**
     * Get transport mode label in Romanian
     */
    getTransportModeLabel(mode) {
        const labels = {
            'transit': 'Transport public',
            'driving': 'Cu mașina',
            'walking': 'Pe jos',
            'bicycling': 'Cu bicicleta'
        };
        return labels[mode] || mode;
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

        // Also update the hero carousel to match
        if (index !== this.currentSlide && this.totalSlides > 0) {
            this.currentSlide = index;

            // Update slides
            document.querySelectorAll('.hero-slide').forEach((slide, i) => {
                if (i === index) {
                    slide.classList.remove('opacity-0');
                    slide.classList.add('opacity-100');
                } else {
                    slide.classList.remove('opacity-100');
                    slide.classList.add('opacity-0');
                }
            });

            // Update indicators
            document.querySelectorAll('.hero-indicator').forEach((indicator, i) => {
                if (i === index) {
                    indicator.classList.remove('bg-white/50', 'hover:bg-white/70', 'w-2');
                    indicator.classList.add('bg-white', 'w-6');
                } else {
                    indicator.classList.remove('bg-white', 'w-6');
                    indicator.classList.add('bg-white/50', 'hover:bg-white/70', 'w-2');
                }
            });
        }
    }
};

// Make available globally
window.TicsEventPage = TicsEventPage;
