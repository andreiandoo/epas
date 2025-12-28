/**
 * Ambilet.ro - Header Component
 * Dynamic header with navigation, cart, and user menu
 */

const AmbiletHeader = {
    /**
     * Initialize header
     */
    init() {
        this.render();
        this.bindEvents();
        this.updateUI();
    },

    /**
     * Render header into DOM
     */
    render() {
        const container = document.getElementById('header') || document.querySelector('header');
        if (!container) return;

        container.innerHTML = this.getTemplate();
    },

    /**
     * Get header HTML template
     */
    getTemplate() {
        const isLoggedIn = AmbiletAuth.isLoggedIn();
        const user = AmbiletAuth.getCurrentUser();
        const cartCount = AmbiletCart.getItemCount();
        const isOrganizer = AmbiletAuth.isOrganizer();

        return `
        <header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md border-b border-border">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-16 lg:h-20">
                    <!-- Logo -->
                    <a href="/" class="flex items-center gap-2.5 shrink-0">
                        <div class="w-10 h-10 bg-primary rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                            </svg>
                        </div>
                        <span class="text-xl font-extrabold text-secondary">AMBILET</span>
                    </a>

                    <!-- Desktop Navigation -->
                    <nav class="hidden lg:flex items-center gap-8">
                        <a href="/" class="text-sm font-medium text-secondary hover:text-primary transition-colors">Acasă</a>
                        <div class="nav-item relative">
                            <button class="text-sm font-medium text-muted hover:text-primary transition-colors flex items-center gap-1">
                                Evenimente
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div class="nav-dropdown absolute top-full left-0 pt-2 w-56">
                                <div class="bg-white rounded-xl shadow-xl border border-border py-2">
                                    <a href="/category.html?type=concert" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Concerte</a>
                                    <a href="/category.html?type=festival" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Festivaluri</a>
                                    <a href="/category.html?type=theater" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Teatru</a>
                                    <a href="/category.html?type=sport" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Sport</a>
                                    <a href="/category.html?type=comedy" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Stand-up Comedy</a>
                                </div>
                            </div>
                        </div>
                        <a href="/organizer/landing.html" class="text-sm font-medium text-muted hover:text-primary transition-colors">Pentru Organizatori</a>
                    </nav>

                    <!-- Right Side -->
                    <div class="flex items-center gap-4">
                        <!-- Search (Desktop) -->
                        <div class="hidden md:flex items-center">
                            <div class="relative">
                                <input type="text"
                                       id="header-search"
                                       placeholder="Caută evenimente..."
                                       class="w-64 pl-10 pr-4 py-2.5 bg-surface border-0 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Cart -->
                        <a href="/cart.html" class="relative p-2 hover:bg-surface rounded-xl transition-colors">
                            <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <span data-cart-count class="absolute -top-1 -right-1 w-5 h-5 bg-primary text-white text-xs font-bold rounded-full flex items-center justify-center ${cartCount === 0 ? 'hidden' : ''}">${cartCount}</span>
                        </a>

                        <!-- User Menu -->
                        ${isLoggedIn ? this.getUserMenu(user, isOrganizer) : this.getAuthLinks()}

                        <!-- Mobile Menu Button -->
                        <button id="mobile-menu-btn" class="lg:hidden p-2 hover:bg-surface rounded-xl transition-colors">
                            <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div id="mobile-menu" class="mobile-menu fixed inset-y-0 right-0 w-80 max-w-full bg-white shadow-2xl lg:hidden z-50">
                <div class="flex flex-col h-full">
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <span class="text-lg font-bold text-secondary">Meniu</span>
                        <button id="close-mobile-menu" class="p-2 hover:bg-surface rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4">
                        <!-- Mobile Search -->
                        <div class="relative mb-6">
                            <input type="text" placeholder="Caută evenimente..." class="w-full pl-10 pr-4 py-3 bg-surface rounded-xl text-sm">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>

                        <!-- Mobile Nav Links -->
                        <nav class="space-y-2">
                            <a href="/" class="block px-4 py-3 text-secondary font-medium rounded-xl hover:bg-surface">Acasă</a>
                            <a href="/category.html?type=concert" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Concerte</a>
                            <a href="/category.html?type=festival" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Festivaluri</a>
                            <a href="/category.html?type=theater" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Teatru</a>
                            <a href="/category.html?type=sport" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Sport</a>
                            <a href="/organizer/landing.html" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Pentru Organizatori</a>
                        </nav>
                    </div>

                    <div class="p-4 border-t border-border">
                        ${isLoggedIn
                            ? `<a href="${isOrganizer ? '/organizer/dashboard.html' : '/user/dashboard.html'}" class="btn btn-primary w-full">Contul meu</a>`
                            : `<a href="/login.html" class="btn btn-primary w-full">Autentificare</a>`
                        }
                    </div>
                </div>
            </div>

            <!-- Mobile Menu Overlay -->
            <div id="mobile-overlay" class="mobile-overlay fixed inset-0 bg-black/50 lg:hidden z-40"></div>
        </header>

        <!-- Spacer for fixed header -->
        <div class="h-16 lg:h-20"></div>
        `;
    },

    /**
     * Get authenticated user menu
     */
    getUserMenu(user, isOrganizer) {
        const displayName = user?.first_name || user?.name || user?.email?.split('@')[0] || 'Cont';

        return `
        <div class="nav-item relative hidden lg:block">
            <button class="flex items-center gap-2 p-2 hover:bg-surface rounded-xl transition-colors">
                <div class="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                    <span class="text-sm font-bold text-primary">${displayName.charAt(0).toUpperCase()}</span>
                </div>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div class="nav-dropdown absolute top-full right-0 pt-2 w-56">
                <div class="bg-white rounded-xl shadow-xl border border-border py-2">
                    <div class="px-4 py-2 border-b border-border">
                        <p class="text-sm font-semibold text-secondary">${displayName}</p>
                        <p class="text-xs text-muted">${user?.email || ''}</p>
                    </div>
                    ${isOrganizer
                        ? `<a href="/organizer/dashboard.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Dashboard</a>
                           <a href="/organizer/events.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Evenimentele mele</a>
                           <a href="/organizer/finance.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Finanțe</a>`
                        : `<a href="/user/dashboard.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Contul meu</a>
                           <a href="/user/tickets.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Biletele mele</a>
                           <a href="/user/orders.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Comenzile mele</a>`
                    }
                    <a href="/user/settings.html" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Setări</a>
                    <div class="border-t border-border mt-2 pt-2">
                        <button id="logout-btn" class="block w-full text-left px-4 py-2 text-sm text-error hover:bg-red-50 transition-colors">Deconectare</button>
                    </div>
                </div>
            </div>
        </div>
        `;
    },

    /**
     * Get login/register links for non-authenticated users
     */
    getAuthLinks() {
        return `
        <div class="hidden lg:flex items-center gap-3">
            <a href="/login.html" class="text-sm font-medium text-muted hover:text-primary transition-colors">Autentificare</a>
            <a href="/register.html" class="btn btn-primary btn-sm">Înregistrare</a>
        </div>
        `;
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn')?.addEventListener('click', () => this.toggleMobileMenu(true));
        document.getElementById('close-mobile-menu')?.addEventListener('click', () => this.toggleMobileMenu(false));
        document.getElementById('mobile-overlay')?.addEventListener('click', () => this.toggleMobileMenu(false));

        // Logout button
        document.getElementById('logout-btn')?.addEventListener('click', () => this.handleLogout());

        // Search
        document.getElementById('header-search')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const query = e.target.value.trim();
                if (query) {
                    window.location.href = `/index.html?search=${encodeURIComponent(query)}`;
                }
            }
        });

        // Listen for auth changes
        window.addEventListener('ambilet:auth:login', () => this.updateUI());
        window.addEventListener('ambilet:auth:logout', () => this.updateUI());

        // Listen for cart changes
        window.addEventListener('ambilet:cart:update', () => this.updateCartBadge());
    },

    /**
     * Toggle mobile menu
     */
    toggleMobileMenu(show) {
        const menu = document.getElementById('mobile-menu');
        const overlay = document.getElementById('mobile-overlay');

        if (show) {
            menu?.classList.add('active');
            overlay?.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            menu?.classList.remove('active');
            overlay?.classList.remove('active');
            document.body.style.overflow = '';
        }
    },

    /**
     * Handle logout
     */
    async handleLogout() {
        await AmbiletAuth.logout();
        window.location.href = '/';
    },

    /**
     * Update UI based on auth state
     */
    updateUI() {
        this.render();
        this.bindEvents();
    },

    /**
     * Update cart badge count
     */
    updateCartBadge() {
        const count = AmbiletCart.getItemCount();
        const badges = document.querySelectorAll('[data-cart-count]');

        badges.forEach(badge => {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        });
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AmbiletHeader.init();
});
