import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

/**
 * SLEEK TEMPLATE - A premium, modern, mobile-first event ticketing experience
 * Designed to feel like a high-end ticketing app (like Dice, Eventbrite, or StubHub)
 *
 * HYBRID APPROACH:
 * - Tailwind classes for HTML structure and layout
 * - CSS custom properties for theming
 * - CSS for animations and complex overrides
 */
const sleekTemplate: TemplateConfig = {
    name: 'sleek',

    // Layout classes using Tailwind
    headerClass: 'fixed top-0 left-0 right-0 z-50 bg-[#0a0a0f]/80 backdrop-blur-xl border-b border-white/[0.08]',
    footerClass: 'bg-[#12121a] border-t border-white/[0.08] mt-16',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',

    // Hero classes
    heroClass: 'text-center py-16 bg-gradient-to-br from-[#0a0a0f] to-[#12121a]',
    heroTitleClass: 'text-4xl md:text-5xl font-bold text-white mb-4 tracking-tight',
    heroSubtitleClass: 'text-xl text-slate-400 mb-8 max-w-2xl mx-auto',

    // Card classes
    cardClass: 'bg-[#12121a] border border-white/[0.08] rounded-2xl overflow-hidden transition-all duration-300',
    cardHoverClass: 'hover:border-white/[0.12] hover:-translate-y-1 hover:shadow-2xl hover:shadow-black/30',

    // Button classes
    primaryButtonClass: 'inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-500 text-white font-semibold rounded-full transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-indigo-500/25',
    secondaryButtonClass: 'inline-flex items-center gap-2 px-6 py-3 bg-transparent border border-white/[0.12] text-white font-semibold rounded-full transition-all duration-300 hover:bg-white/5 hover:border-white/20',

    // Typography classes
    headingClass: 'text-2xl font-bold text-white tracking-tight',
    subheadingClass: 'text-lg text-slate-400',

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'Tixello';
        const headerMenu = config.menus?.header || [];

        const menuItemsHtml = headerMenu.map(item =>
            `<a href="${item.url}" class="px-4 py-2 text-slate-400 hover:text-white text-sm font-medium rounded-lg transition-colors">${item.title}</a>`
        ).join('');

        return `
            <style>
                /* ========================================
                   CSS VARIABLES & THEME
                   ======================================== */
                :root {
                    --sleek-bg: #0a0a0f;
                    --sleek-surface: #12121a;
                    --sleek-surface-elevated: #1a1a24;
                    --sleek-border: rgba(255,255,255,0.08);
                    --sleek-border-light: rgba(255,255,255,0.12);
                    --sleek-text: #ffffff;
                    --sleek-text-muted: #94a3b8;
                    --sleek-text-subtle: #64748b;
                    --sleek-gradient-start: var(--tixello-primary, #6366f1);
                    --sleek-gradient-end: var(--tixello-primary-dark, #8b5cf6);
                    --sleek-success: #10b981;
                    --sleek-warning: #f59e0b;
                    --sleek-error: #ef4444;
                    --sleek-glow: rgba(99, 102, 241, 0.15);
                }

                /* Global Dark Theme Override */
                body {
                    background: var(--sleek-bg) !important;
                    color: var(--sleek-text) !important;
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                }

                #tixello-app {
                    background: var(--sleek-bg);
                    min-height: 100vh;
                }

                /* Main content area spacing */
                main, #tixello-content {
                    padding-top: 64px;
                    min-height: 100vh;
                    background: var(--sleek-bg);
                }

                @media (min-width: 768px) {
                    main, #tixello-content {
                        padding-top: 72px;
                    }
                }

                @media (max-width: 767px) {
                    main, #tixello-content {
                        padding-bottom: 80px;
                    }
                }

                /* ========================================
                   TAILWIND DARK MODE OVERRIDES
                   ======================================== */
                .text-gray-900, .text-gray-800 { color: var(--sleek-text) !important; }
                .text-gray-700 { color: rgba(255,255,255,0.9) !important; }
                .text-gray-600, .text-gray-500 { color: var(--sleek-text-muted) !important; }
                .text-gray-400 { color: var(--sleek-text-subtle) !important; }

                .bg-gray-50 { background: var(--sleek-bg) !important; }
                .bg-gray-100 { background: var(--sleek-surface) !important; }
                .bg-gray-200 { background: var(--sleek-surface-elevated) !important; }
                .bg-white { background: var(--sleek-surface) !important; }

                .border-gray-100, .border-gray-200 { border-color: var(--sleek-border) !important; }
                .border-gray-300 { border-color: var(--sleek-border-light) !important; }

                .shadow-sm, .shadow, .shadow-md, .shadow-lg {
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3) !important;
                }

                .hover\\:shadow-md:hover, .hover\\:shadow-lg:hover {
                    box-shadow: 0 8px 30px rgba(0,0,0,0.4) !important;
                }

                /* Primary button overrides */
                .bg-primary, [class*="bg-primary"] {
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                }

                /* ========================================
                   FORM INPUTS
                   ======================================== */
                input[type="text"], input[type="email"], input[type="password"],
                input[type="tel"], input[type="number"], select, textarea {
                    background: var(--sleek-surface-elevated) !important;
                    border-color: var(--sleek-border) !important;
                    color: var(--sleek-text) !important;
                }

                input:focus, select:focus, textarea:focus {
                    border-color: var(--sleek-gradient-start) !important;
                    box-shadow: 0 0 0 3px var(--sleek-glow) !important;
                    outline: none !important;
                }

                input::placeholder, textarea::placeholder {
                    color: var(--sleek-text-subtle) !important;
                }

                /* ========================================
                   EVENTS PAGE OVERRIDES
                   ======================================== */
                #events-calendar {
                    background: var(--sleek-bg) !important;
                    border-color: var(--sleek-border) !important;
                }

                #days-scroller button, .day-btn {
                    background: var(--sleek-surface) !important;
                    border-color: var(--sleek-border) !important;
                    color: var(--sleek-text-muted) !important;
                }

                #days-scroller button:hover, .day-btn:hover {
                    border-color: var(--sleek-border-light) !important;
                    background: var(--sleek-surface-elevated) !important;
                }

                .day-btn.border-gray-900, [class*="border-gray-900"] {
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                    border-color: var(--sleek-gradient-start) !important;
                    color: white !important;
                }

                .month-btn {
                    background: var(--sleek-surface) !important;
                    border-color: var(--sleek-border) !important;
                    color: var(--sleek-text-muted) !important;
                }

                .month-btn.bg-gray-900 {
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                    border-color: var(--sleek-gradient-start) !important;
                    color: white !important;
                }

                #events-container a, .event-card {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: 16px !important;
                    transition: all 0.3s ease !important;
                }

                #events-container a:hover, .event-card:hover {
                    border-color: var(--sleek-border-light) !important;
                    transform: translateY(-4px) !important;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3) !important;
                }

                /* ========================================
                   CART & CHECKOUT OVERRIDES
                   ======================================== */
                .cart-item, [class*="cart-item"] {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: 16px !important;
                }

                .cart-summary, [class*="cart-summary"], [class*="order-summary"] {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: 16px !important;
                }

                #payment-element, .StripeElement {
                    background: var(--sleek-surface-elevated) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: 12px !important;
                    padding: 1rem !important;
                }

                /* ========================================
                   ADMIN & TABLES
                   ======================================== */
                table { width: 100%; border-collapse: separate; border-spacing: 0; }

                th {
                    background: var(--sleek-surface-elevated) !important;
                    color: var(--sleek-text-muted) !important;
                    font-weight: 600 !important;
                    text-transform: uppercase !important;
                    font-size: 0.75rem !important;
                    padding: 1rem !important;
                    border-bottom: 1px solid var(--sleek-border) !important;
                }

                td {
                    padding: 1rem !important;
                    border-bottom: 1px solid var(--sleek-border) !important;
                    color: var(--sleek-text) !important;
                }

                tr:hover td {
                    background: rgba(255,255,255,0.02) !important;
                }

                /* ========================================
                   STATUS COLORS
                   ======================================== */
                .text-red-500, .text-red-600 { color: var(--sleek-error) !important; }
                .text-green-500, .text-green-600 { color: var(--sleek-success) !important; }
                .bg-green-100, .bg-green-50 { background: rgba(16, 185, 129, 0.15) !important; }
                .bg-red-100, .bg-red-50 { background: rgba(239, 68, 68, 0.15) !important; }

                /* ========================================
                   QUANTITY SELECTORS
                   ======================================== */
                .ticket-plus, .ticket-minus,
                .cart-qty-plus, .cart-qty-minus,
                .mobile-ticket-plus, .mobile-ticket-minus {
                    min-width: 40px !important;
                    min-height: 40px !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    background: var(--sleek-surface-elevated) !important;
                    border: 1px solid var(--sleek-border) !important;
                    color: var(--sleek-text) !important;
                    border-radius: 8px !important;
                    font-size: 1.25rem !important;
                    cursor: pointer !important;
                    transition: all 0.2s ease !important;
                }

                .ticket-plus:hover, .ticket-minus:hover,
                .cart-qty-plus:hover, .cart-qty-minus:hover {
                    background: rgba(255,255,255,0.1) !important;
                    border-color: var(--sleek-border-light) !important;
                }

                .ticket-qty-display, .mobile-ticket-qty {
                    min-width: 40px !important;
                    text-align: center !important;
                    font-weight: 600 !important;
                    color: var(--sleek-text) !important;
                }

                /* ========================================
                   ANIMATIONS
                   ======================================== */
                @keyframes sleek-fade-in {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @keyframes sleek-slide-in-right {
                    from { opacity: 0; transform: translateX(100%); }
                    to { opacity: 1; transform: translateX(0); }
                }

                @keyframes sleek-slide-in-up {
                    from { opacity: 0; transform: translateY(100%); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @keyframes sleek-shimmer {
                    0% { background-position: 200% 0; }
                    100% { background-position: -200% 0; }
                }

                @keyframes sleek-pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.6; }
                }

                #tixello-content {
                    animation: sleek-fade-in 0.4s ease-out;
                }

                .animate-pulse {
                    background: var(--sleek-surface-elevated) !important;
                    animation: sleek-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite !important;
                }

                /* ========================================
                   TOAST NOTIFICATIONS
                   ======================================== */
                .sleek-toast-container {
                    position: fixed;
                    top: 80px;
                    right: 1rem;
                    z-index: 1000;
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                    pointer-events: none;
                }

                @media (max-width: 767px) {
                    .sleek-toast-container {
                        top: auto;
                        bottom: 100px;
                        left: 1rem;
                        right: 1rem;
                    }
                }

                .sleek-toast {
                    pointer-events: auto;
                    animation: sleek-slide-in-right 0.4s cubic-bezier(0.32, 0.72, 0, 1);
                }

                @media (max-width: 767px) {
                    .sleek-toast {
                        animation: sleek-slide-in-up 0.4s cubic-bezier(0.32, 0.72, 0, 1);
                    }
                }

                /* ========================================
                   MODAL DIALOGS
                   ======================================== */
                .sleek-modal-overlay {
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease;
                }

                .sleek-modal-overlay.active {
                    opacity: 1;
                    visibility: visible;
                }

                .sleek-modal {
                    transform: scale(0.95) translateY(10px);
                    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
                }

                .sleek-modal-overlay.active .sleek-modal {
                    transform: scale(1) translateY(0);
                }

                /* ========================================
                   SKELETON LOADING
                   ======================================== */
                .sleek-skeleton {
                    background: linear-gradient(90deg, var(--sleek-surface-elevated) 0%, rgba(255,255,255,0.05) 50%, var(--sleek-surface-elevated) 100%);
                    background-size: 200% 100%;
                    animation: sleek-shimmer 1.5s infinite;
                    border-radius: 8px;
                }

                /* ========================================
                   MOBILE DRAWER (Tailwind class support)
                   ======================================== */
                .sleek-drawer.translate-x-full {
                    transform: translateX(100%);
                }

                .sleek-drawer-overlay.hidden {
                    display: none;
                }

                .sleek-drawer-overlay.opacity-0 {
                    opacity: 0;
                }

                /* ========================================
                   PRINT STYLES
                   ======================================== */
                @media print {
                    .sleek-header, .sleek-footer, .sleek-bottom-nav,
                    .sleek-drawer-overlay, .sleek-drawer,
                    .sleek-toast-container, button {
                        display: none !important;
                    }

                    body, #tixello-app, main, #tixello-content {
                        background: white !important;
                        color: black !important;
                        padding: 0 !important;
                    }

                    canvas, img {
                        max-width: 100% !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                }

                /* ========================================
                   ACCESSIBILITY
                   ======================================== */
                @media (prefers-reduced-motion: reduce) {
                    *, *::before, *::after {
                        animation-duration: 0.01ms !important;
                        transition-duration: 0.01ms !important;
                    }
                }

                *:focus-visible {
                    outline: 2px solid var(--sleek-gradient-start);
                    outline-offset: 2px;
                }

                ::selection {
                    background: var(--sleek-gradient-start);
                    color: white;
                }

                html {
                    scroll-behavior: smooth;
                }

                /* PWA safe areas */
                @supports (padding: env(safe-area-inset-bottom)) {
                    .sleek-bottom-nav {
                        padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
                    }
                }
            </style>

            <!-- Toast Container -->
            <div id="sleek-toast-container" class="sleek-toast-container"></div>

            <!-- Header with Tailwind classes -->
            <header class="fixed top-0 left-0 right-0 z-50 bg-[#0a0a0f]/80 backdrop-blur-xl border-b border-white/[0.08]">
                <div class="max-w-[1400px] mx-auto px-4 md:px-8 h-16 md:h-[72px] flex items-center justify-between">
                    <!-- Logo -->
                    <a href="/" class="flex items-center gap-3 text-white font-bold text-xl tracking-tight">
                        ${logo
                            ? `<img src="${logo}" alt="${siteName}" class="h-8 md:h-9 w-auto">`
                            : `<span>${siteName}</span>`
                        }
                    </a>

                    <!-- Desktop Search -->
                    <div class="hidden lg:block relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                            class="w-[220px] focus:w-[280px] pl-10 pr-4 py-2.5 bg-white/5 border border-white/[0.08] rounded-full text-white text-sm placeholder-slate-500 transition-all duration-300 focus:bg-[#1a1a24] focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            placeholder="Cauta evenimente..."
                            id="sleek-search-input">
                    </div>

                    <!-- Desktop Nav -->
                    <nav class="hidden md:flex items-center gap-1">
                        <a href="/events" class="px-4 py-2 text-slate-400 hover:text-white text-sm font-medium rounded-lg transition-colors">Evenimente</a>
                        <a href="/blog" class="px-4 py-2 text-slate-400 hover:text-white text-sm font-medium rounded-lg transition-colors">Blog</a>
                        ${menuItemsHtml}
                    </nav>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <!-- Mobile Search Button -->
                        <button class="lg:hidden flex items-center justify-center w-11 h-11 text-slate-400 hover:text-white hover:bg-white/5 rounded-lg transition-colors" id="mobile-search-btn" aria-label="Search">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>

                        <!-- Cart -->
                        <a href="/cart" class="relative flex items-center justify-center w-11 h-11 bg-white/5 border border-white/[0.08] rounded-full text-white hover:bg-white/10 hover:border-white/[0.12] hover:scale-105 transition-all" aria-label="Shopping Cart">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span id="cart-badge" class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-lg shadow-indigo-500/30 hidden">0</span>
                        </a>

                        <!-- Account (Desktop) -->
                        <a href="/account" id="account-link" class="hidden md:flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-sm font-semibold rounded-full hover:-translate-y-0.5 hover:shadow-lg hover:shadow-indigo-500/25 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>Contul meu</span>
                        </a>

                        <!-- Mobile Menu Button -->
                        <button class="md:hidden flex items-center justify-center w-11 h-11 text-white hover:bg-white/5 rounded-lg transition-colors" id="mobile-menu-btn" aria-label="Open Menu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Mobile Search Overlay -->
            <div id="mobile-search-overlay" class="fixed inset-0 bg-[#0a0a0f] z-[300] p-4 -translate-y-full transition-transform duration-300" style="transition-timing-function: cubic-bezier(0.32, 0.72, 0, 1);">
                <div class="flex items-center gap-3 mb-4">
                    <input type="text" class="flex-1 px-4 py-3.5 bg-[#12121a] border border-white/[0.08] rounded-xl text-white placeholder-slate-500 focus:border-indigo-500 focus:outline-none" placeholder="Cauta evenimente..." id="mobile-search-input">
                    <button class="px-4 py-2 text-indigo-400 font-medium" id="mobile-search-cancel">Anuleaza</button>
                </div>
                <div id="mobile-search-results"></div>
            </div>

            <!-- Mobile Drawer Overlay -->
            <div id="mobile-menu-overlay" class="sleek-drawer-overlay fixed inset-0 bg-black/60 backdrop-blur-sm z-[200] hidden opacity-0 transition-opacity duration-300"></div>

            <!-- Mobile Drawer -->
            <div id="mobile-menu-drawer" class="sleek-drawer fixed top-0 right-0 bottom-0 w-[300px] max-w-[85vw] bg-[#12121a] border-l border-white/[0.08] z-[201] translate-x-full flex flex-col transition-transform duration-400" style="transition-timing-function: cubic-bezier(0.32, 0.72, 0, 1);">
                <div class="flex items-center justify-between p-4 border-b border-white/[0.08]">
                    <span class="font-semibold text-white">Menu</span>
                    <button id="mobile-menu-close" class="w-9 h-9 flex items-center justify-center bg-white/5 rounded-full text-slate-400 hover:text-white hover:bg-white/10 transition-colors" aria-label="Close Menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 p-4 overflow-y-auto">
                    <a href="/events" class="flex items-center gap-3 px-4 py-3.5 text-white font-medium rounded-xl hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Evenimente
                    </a>
                    <a href="/blog" class="flex items-center gap-3 px-4 py-3.5 text-white font-medium rounded-xl hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                        </svg>
                        Blog
                    </a>
                    ${headerMenu.map(item => `
                        <a href="${item.url}" class="flex items-center gap-3 px-4 py-3.5 text-white font-medium rounded-xl hover:bg-white/5 transition-colors mb-1">
                            <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            ${item.title}
                        </a>
                    `).join('')}
                    <div class="h-px bg-white/[0.08] my-4"></div>
                    <a href="/account" class="flex items-center gap-3 px-4 py-3.5 text-white font-medium rounded-xl hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Contul meu
                    </a>
                    <a href="/account/orders" class="flex items-center gap-3 px-4 py-3.5 text-white font-medium rounded-xl hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Comenzile mele
                    </a>
                    <a href="/account/tickets" class="flex items-center gap-3 px-4 py-3.5 text-white font-medium rounded-xl hover:bg-white/5 transition-colors mb-1">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                        Biletele mele
                    </a>
                </nav>
                <div class="p-4 border-t border-white/[0.08]">
                    <a href="/cart" class="flex items-center justify-center gap-2 w-full px-4 py-3.5 bg-gradient-to-r from-indigo-500 to-purple-500 text-white font-semibold rounded-xl hover:shadow-lg hover:shadow-indigo-500/25 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Vezi Cosul
                        <span id="cart-badge-menu" class="ml-auto px-2 py-0.5 bg-white/20 text-white text-xs font-bold rounded-full hidden">0</span>
                    </a>
                </div>
            </div>

            <!-- Mobile Bottom Navigation -->
            <nav class="sleek-bottom-nav md:hidden fixed bottom-0 left-0 right-0 z-[90] flex bg-[#12121a]/95 backdrop-blur-xl border-t border-white/[0.08] py-2">
                <a href="/" class="flex-1 flex flex-col items-center gap-1 py-2 text-slate-400 hover:text-indigo-400 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="text-[10px] font-medium">Acasa</span>
                </a>
                <a href="/events" class="flex-1 flex flex-col items-center gap-1 py-2 text-slate-400 hover:text-indigo-400 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span class="text-[10px] font-medium">Descopera</span>
                </a>
                <a href="/account/tickets" class="flex-1 flex flex-col items-center gap-1 py-2 text-slate-400 hover:text-indigo-400 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                    <span class="text-[10px] font-medium">Bilete</span>
                </a>
                <a href="/account" class="flex-1 flex flex-col items-center gap-1 py-2 text-slate-400 hover:text-indigo-400 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span class="text-[10px] font-medium">Cont</span>
                </a>
            </nav>
        `;
    },

    renderFooter: (config: TixelloConfig): string => {
        const siteName = config.site?.title || 'Tixello';
        const year = new Date().getFullYear();
        const social = config.social || {};
        const footerMenu = config.menus?.footer || [];

        const socialIcons = [];
        if (social.facebook) {
            socialIcons.push(`<a href="${social.facebook}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-white/5 border border-white/[0.08] rounded-full text-slate-400 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white hover:-translate-y-0.5 transition-all" aria-label="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>`);
        }
        if (social.instagram) {
            socialIcons.push(`<a href="${social.instagram}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-white/5 border border-white/[0.08] rounded-full text-slate-400 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white hover:-translate-y-0.5 transition-all" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>`);
        }
        if (social.twitter) {
            socialIcons.push(`<a href="${social.twitter}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-white/5 border border-white/[0.08] rounded-full text-slate-400 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white hover:-translate-y-0.5 transition-all" aria-label="Twitter"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>`);
        }
        if (social.youtube) {
            socialIcons.push(`<a href="${social.youtube}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-white/5 border border-white/[0.08] rounded-full text-slate-400 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white hover:-translate-y-0.5 transition-all" aria-label="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>`);
        }
        if (social.tiktok) {
            socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-white/5 border border-white/[0.08] rounded-full text-slate-400 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white hover:-translate-y-0.5 transition-all" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>`);
        }
        if (social.linkedin) {
            socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="w-10 h-10 flex items-center justify-center bg-white/5 border border-white/[0.08] rounded-full text-slate-400 hover:bg-indigo-500 hover:border-indigo-500 hover:text-white hover:-translate-y-0.5 transition-all" aria-label="LinkedIn"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>`);
        }

        const socialHtml = socialIcons.length > 0
            ? `<div class="flex gap-3 mt-4">${socialIcons.join('')}</div>`
            : '';

        const footerMenuHtml = footerMenu.map(item =>
            `<li><a href="${item.url}" class="text-slate-400 hover:text-white transition-colors">${item.title}</a></li>`
        ).join('');

        return `
            <footer class="bg-[#12121a] border-t border-white/[0.08] mt-16 mb-16 md:mb-0">
                <div class="max-w-[1400px] mx-auto px-4 md:px-8 py-12 md:py-16">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
                        <!-- Brand -->
                        <div class="col-span-2">
                            <h3 class="text-xl font-bold text-white mb-4">${siteName}</h3>
                            <p class="text-slate-400 text-sm leading-relaxed mb-4 max-w-sm">${config.site?.description || 'Descoperiti cele mai bune evenimente si cumparati bilete online.'}</p>
                            ${socialHtml}
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h4 class="text-xs font-semibold text-white uppercase tracking-wider mb-4">Navigare</h4>
                            <ul class="space-y-3 text-sm">
                                <li><a href="/events" class="text-slate-400 hover:text-white transition-colors">Evenimente</a></li>
                                <li><a href="/past-events" class="text-slate-400 hover:text-white transition-colors">Evenimente trecute</a></li>
                                <li><a href="/blog" class="text-slate-400 hover:text-white transition-colors">Blog</a></li>
                                <li><a href="/account" class="text-slate-400 hover:text-white transition-colors">Contul meu</a></li>
                            </ul>
                        </div>

                        <!-- Legal -->
                        <div>
                            <h4 class="text-xs font-semibold text-white uppercase tracking-wider mb-4">Legal</h4>
                            <ul class="space-y-3 text-sm">
                                <li><a href="/terms" class="text-slate-400 hover:text-white transition-colors">Termeni si conditii</a></li>
                                <li><a href="/privacy" class="text-slate-400 hover:text-white transition-colors">Confidentialitate</a></li>
                                ${footerMenuHtml}
                            </ul>
                        </div>
                    </div>

                    <!-- Bottom -->
                    <div class="mt-12 pt-8 border-t border-white/[0.08] flex flex-col sm:flex-row gap-4 items-center justify-between text-sm text-slate-500">
                        <p>&copy; ${year} ${siteName}. Toate drepturile rezervate.</p>
                        <div class="flex items-center gap-2">
                            <span>Powered by</span>
                            <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank" rel="noopener noreferrer" class="text-indigo-400 font-semibold hover:text-indigo-300 transition-colors">
                                ${config.platform?.name || 'Tixello'}
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        `;
    }
};

// Register the template
TemplateManager.registerTemplate('sleek', sleekTemplate);

export default sleekTemplate;
