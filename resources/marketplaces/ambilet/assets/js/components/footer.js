/**
 * Ambilet.ro - Footer Component
 * Site footer with links and info
 */

const AmbiletFooter = {
    /**
     * Initialize footer
     */
    init() {
        this.render();
    },

    /**
     * Render footer into DOM
     */
    render() {
        const container = document.getElementById('footer') || document.querySelector('footer');
        if (!container) return;

        container.innerHTML = this.getTemplate();
    },

    /**
     * Get footer HTML template
     */
    getTemplate() {
        const currentYear = new Date().getFullYear();

        return `
        <footer class="bg-secondary text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-12 lg:py-16">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-8">
                    <!-- Brand -->
                    <div class="col-span-2 lg:col-span-1">
                        <a href="/" class="flex items-center gap-2.5 mb-4">
                            <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                            </div>
                            <span class="text-xl font-extrabold">AMBILET</span>
                        </a>
                        <p class="text-white/90 text-sm mb-6">
                            Platformă de ticketing pentru evenimente din România. Bilete pentru concerte, festivaluri, teatru și multe altele.
                        </p>
                        <div class="flex gap-4">
                            <a href="${AMBILET_CONFIG.SOCIAL.FACEBOOK}" target="_blank" rel="noopener" aria-label="Facebook" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-primary transition-colors">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </a>
                            <a href="${AMBILET_CONFIG.SOCIAL.INSTAGRAM}" target="_blank" rel="noopener" aria-label="Instagram" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-primary transition-colors">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                            </a>
                            <a href="${AMBILET_CONFIG.SOCIAL.TWITTER}" target="_blank" rel="noopener" aria-label="X (Twitter)" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center hover:bg-primary transition-colors">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    <!-- Evenimente -->
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wider mb-4">Evenimente</h4>
                        <ul class="space-y-3">
                            <li><a href="/category.php?type=concert" class="text-white/90 hover:text-white text-sm transition-colors">Concerte</a></li>
                            <li><a href="/category.php?type=festival" class="text-white/90 hover:text-white text-sm transition-colors">Festivaluri</a></li>
                            <li><a href="/category.php?type=theater" class="text-white/90 hover:text-white text-sm transition-colors">Teatru</a></li>
                            <li><a href="/category.php?type=sport" class="text-white/90 hover:text-white text-sm transition-colors">Sport</a></li>
                            <li><a href="/category.php?type=comedy" class="text-white/90 hover:text-white text-sm transition-colors">Stand-up Comedy</a></li>
                        </ul>
                    </div>

                    <!-- Contul meu -->
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wider mb-4">Contul meu</h4>
                        <ul class="space-y-3">
                            <li><a href="/login.php" class="text-white/90 hover:text-white text-sm transition-colors">Autentificare</a></li>
                            <li><a href="/register.php" class="text-white/90 hover:text-white text-sm transition-colors">Înregistrare</a></li>
                            <li><a href="/user/tickets.php" class="text-white/90 hover:text-white text-sm transition-colors">Biletele mele</a></li>
                            <li><a href="/user/orders.php" class="text-white/90 hover:text-white text-sm transition-colors">Comenzile mele</a></li>
                            <li><a href="/user/rewards.php" class="text-white/90 hover:text-white text-sm transition-colors">Puncte fideltate</a></li>
                        </ul>
                    </div>

                    <!-- Organizatori -->
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wider mb-4">Organizatori</h4>
                        <ul class="space-y-3">
                            <li><a href="/organizator/landing" class="text-white/90 hover:text-white text-sm transition-colors">Vinde bilete</a></li>
                            <li><a href="/organizator/register" class="text-white/90 hover:text-white text-sm transition-colors">Înregistrare organizator</a></li>
                            <li><a href="/organizator/login" class="text-white/90 hover:text-white text-sm transition-colors">Login organizator</a></li>
                            <li><a href="/organizator/help" class="text-white/90 hover:text-white text-sm transition-colors">Ghid organizatori</a></li>
                        </ul>
                    </div>

                    <!-- Suport -->
                    <div>
                        <h4 class="text-sm font-semibold uppercase tracking-wider mb-4">Suport</h4>
                        <ul class="space-y-3">
                            <li><a href="/user/help.php" class="text-white/90 hover:text-white text-sm transition-colors">Întrebări frecvente</a></li>
                            <li><a href="/contact.php" class="text-white/90 hover:text-white text-sm transition-colors">Contact</a></li>
                            <li><a href="/terms.php" class="text-white/90 hover:text-white text-sm transition-colors">Termeni și condiții</a></li>
                            <li><a href="/privacy.php" class="text-white/90 hover:text-white text-sm transition-colors">Politica de confidențialitate</a></li>
                            <li><a href="/cookies.php" class="text-white/90 hover:text-white text-sm transition-colors">Politica cookies</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Payment methods & Trust badges -->
                <div class="mt-12 pt-8 border-t border-white/10">
                    <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                        <div class="flex items-center gap-4">
                            <span class="text-white/90 text-sm">Plăți securizate:</span>
                            <div class="flex items-center gap-3">
                                <img src="https://cdn.jsdelivr.net/gh/lipis/flag-icons/flags/4x3/visa.svg" alt="Visa" class="h-6 opacity-70 hover:opacity-100 transition-opacity">
                                <img src="https://cdn.jsdelivr.net/gh/lipis/flag-icons/flags/4x3/mastercard.svg" alt="Mastercard" class="h-6 opacity-70 hover:opacity-100 transition-opacity">
                                <svg class="h-6 opacity-70 hover:opacity-100 transition-opacity" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.545 10.239v3.821h5.445c-.712 2.315-2.647 3.972-5.445 3.972a6.033 6.033 0 110-12.064c1.498 0 2.866.549 3.921 1.453l2.814-2.814A9.969 9.969 0 0012.545 2C6.477 2 1.545 6.932 1.545 13s4.932 11 11 11c6.068 0 10.955-4.932 10.955-11 0-.738-.082-1.461-.231-2.161l-10.724-.6z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            <div class="flex items-center gap-2 text-white/90 text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span>SSL 256-bit</span>
                            </div>
                            <div class="flex items-center gap-2 text-white/90 text-sm">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span>Date protejate</span>
                            </div>
                        </div>
                    </div>
                </div>
 
                <!-- Copyright -->
                <div class="mt-8 pt-8 border-t border-white/10">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                        <p class="text-white/90 text-sm">
                            &copy; ${currentYear} Ambilet. Toate drepturile rezervate.
                        </p>
                        <p class="text-white/90 text-sm">
                            Operat de <a href="https://tixello.com" target="_blank" rel="noopener" class="text-primary hover:text-primary-light transition-colors">Tixello</a>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
        `;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    AmbiletFooter.init();
});
