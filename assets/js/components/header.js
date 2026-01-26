/**
 * Ambilet.ro - Header Component
 * Dynamic header with navigation, cart, and user menu
 */

const AmbiletHeader = {
    /**
     * Initialize header
     */
    init() {
        // Skip JS header if PHP mega-header is already rendered
        // PHP header includes #cartDrawer and #searchOverlay elements
        if (document.getElementById('cartDrawer') && document.getElementById('searchOverlay')) {
            console.log('[AmbiletHeader] PHP mega-header detected, skipping JS render');
            this.bindAuthEvents();
            return;
        }

        this.render();
        this.bindEvents();
        this.updateUI();
    },

    /**
     * Bind only auth-related events (for PHP header)
     * Used when PHP header is present but we still need auth state updates
     */
    bindAuthEvents() {
        // Listen for auth changes to update user menu
        window.addEventListener('ambilet:auth:login', () => this.updatePHPHeaderAuth());
        window.addEventListener('ambilet:auth:logout', () => this.updatePHPHeaderAuth());

        // Initial auth state check
        this.updatePHPHeaderAuth();
    },

    /**
     * Update PHP header's auth state
     */
    updatePHPHeaderAuth() {
        const isLoggedIn = typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isLoggedIn();
        const loginLink = document.querySelector('a[href="/login"]');
        const userMenu = document.getElementById('headerUserMenu');
        const loginBtn = document.getElementById('loginBtn');

        if (isLoggedIn && userMenu) {
            const user = AmbiletAuth.getCurrentUser();
            loginLink?.closest('.hidden')?.classList.add('!hidden');
            loginBtn?.classList.remove('sm:flex');
            userMenu.style.display = '';

            // Update user info
            const initialsEl = document.getElementById('headerUserInitials');
            const nameEl = document.getElementById('headerUserName');
            const emailEl = document.getElementById('headerUserEmail');

            if (initialsEl && user) {
                const name = user.first_name || user.name || user.email?.split('@')[0] || 'U';
                initialsEl.textContent = name.charAt(0).toUpperCase();
            }
            if (nameEl && user) {
                nameEl.textContent = user.first_name || user.name || user.email?.split('@')[0] || 'Utilizator';
            }
            if (emailEl && user) {
                emailEl.textContent = user.email || '';
            }
        } else if (userMenu) {
            userMenu.style.display = 'none';
            loginLink?.closest('.hidden')?.classList.remove('!hidden');
        }
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
                                    <a href="/concerte" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Concerte</a>
                                    <a href="/festivaluri" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Festivaluri</a>
                                    <a href="/teatru" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Teatru</a>
                                    <a href="/sport" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Sport</a>
                                    <a href="/stand-up" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Stand-up Comedy</a>
                                </div>
                            </div>
                        </div>
                        <a href="/organizatori" class="text-sm font-medium text-muted hover:text-primary transition-colors">Pentru Organizatori</a>
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
                        <a href="/cart" class="relative p-2 hover:bg-surface rounded-xl transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-6 h-6 text-secondary"><g><path d="M29.31 13.44H30V9.785a3.214 3.214 0 0 0-3.215-3.215h-5.447v3.5a.9.9 0 0 1-1.8 0v-3.5H5.215A3.214 3.214 0 0 0 2 9.785v3.655h.69a2.56 2.56 0 1 1 0 5.12H2v3.655a3.214 3.214 0 0 0 3.215 3.215h14.322v-3.5a.9.9 0 0 1 1.8 0v3.5h5.447a3.214 3.214 0 0 0 3.215-3.215V18.56h-.69a2.56 2.56 0 1 1 .001-5.12zm-7.97 4.37a.9.9 0 0 1-1.799 0v-3.62a.9.9 0 0 1 1.799 0z" fill="currentColor" opacity="1" class=""></path></g></svg>
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
                            <a href="/concerte" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Concerte</a>
                            <a href="/festivaluri" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Festivaluri</a>
                            <a href="/teatru" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Teatru</a>
                            <a href="/sport" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Sport</a>
                            <a href="/organizatori" class="block px-4 py-3 text-muted rounded-xl hover:bg-surface hover:text-primary">Pentru Organizatori</a>
                        </nav>
                    </div>

                    <div class="p-4 border-t border-border">
                        ${isLoggedIn
                            ? `<a href="${isOrganizer ? '/organizator/dashboard' : '/user/dashboard'}" class="btn btn-primary w-full">Contul meu</a>`
                            : `<a href="/login" class="btn btn-primary w-full">Autentificare</a>`
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
                        ? `<a href="/organizator/dashboard" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Dashboard</a>
                           <a href="/organizator/events" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Evenimentele mele</a>
                           <a href="/organizator/finance" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Finanțe</a>`
                        : `<a href="/user/dashboard" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Contul meu</a>
                           <a href="/user/tickets" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Biletele mele</a>
                           <a href="/user/orders" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Comenzile mele</a>`
                    }
                    <a href="/user/settings" class="block px-4 py-2 text-sm text-secondary hover:bg-surface hover:text-primary transition-colors">Setări</a>
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
            <a href="/login" class="text-sm font-medium text-muted hover:text-primary transition-colors">Autentificare</a>
            <a href="/register" class="btn btn-primary btn-sm">Înregistrare</a>
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
                    window.location.href = `/?search=${encodeURIComponent(query)}`;
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
