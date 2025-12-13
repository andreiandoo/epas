import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

/**
 * SLEEK TEMPLATE - A premium, modern, mobile-first event ticketing experience
 * Designed to feel like a high-end ticketing app (like Dice, Eventbrite, or StubHub)
 *
 * This template provides comprehensive styling for ALL pages including:
 * - Home page with hero and featured events
 * - Events listing with calendar and grid
 * - Event detail pages
 * - Cart and Checkout
 * - User account dashboard and all sub-pages
 * - Blog listing and articles
 * - Admin dashboard (when applicable)
 */
const sleekTemplate: TemplateConfig = {
    name: 'sleek',

    // Layout - Glass morphism with dark mode support
    headerClass: 'sleek-header',
    footerClass: 'sleek-footer',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',

    // Hero - Immersive gradient with animated elements
    heroClass: 'sleek-hero',
    heroTitleClass: 'sleek-hero-title',
    heroSubtitleClass: 'sleek-hero-subtitle',

    // Cards - Elevated with subtle animations
    cardClass: 'sleek-card',
    cardHoverClass: 'sleek-card-hover',

    // Buttons - Rounded, gradient, with micro-interactions
    primaryButtonClass: 'sleek-btn-primary',
    secondaryButtonClass: 'sleek-btn-secondary',

    // Typography - Clean, modern
    headingClass: 'sleek-heading',
    subheadingClass: 'sleek-subheading',

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'Tixello';
        const headerMenu = config.menus?.header || [];

        const menuItemsHtml = headerMenu.map(item =>
            `<a href="${item.url}" class="sleek-nav-link">${item.title}</a>`
        ).join('');

        return `
            <style>
                /* ========================================
                   SLEEK TEMPLATE - CSS DESIGN SYSTEM
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
                    --sleek-radius: 16px;
                    --sleek-radius-sm: 12px;
                    --sleek-radius-xs: 8px;
                    --sleek-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }

                /* Global Dark Theme */
                body {
                    background: var(--sleek-bg) !important;
                    color: var(--sleek-text) !important;
                }

                #tixello-app {
                    background: var(--sleek-bg);
                    min-height: 100vh;
                }

                /* ========================================
                   HEADER - Glassmorphism Navigation
                   ======================================== */
                .sleek-header {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 100;
                    background: rgba(10, 10, 15, 0.8);
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    border-bottom: 1px solid var(--sleek-border);
                }

                .sleek-header-inner {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 0 1rem;
                    height: 64px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }

                @media (min-width: 768px) {
                    .sleek-header-inner {
                        height: 72px;
                        padding: 0 2rem;
                    }
                }

                .sleek-logo {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    text-decoration: none;
                    font-weight: 700;
                    font-size: 1.25rem;
                    color: var(--sleek-text);
                    letter-spacing: -0.02em;
                }

                .sleek-logo img {
                    height: 32px;
                    width: auto;
                }

                @media (min-width: 768px) {
                    .sleek-logo img {
                        height: 36px;
                    }
                }

                .sleek-nav {
                    display: none;
                    align-items: center;
                    gap: 0.5rem;
                }

                @media (min-width: 768px) {
                    .sleek-nav {
                        display: flex;
                    }
                }

                .sleek-nav-link {
                    padding: 0.5rem 1rem;
                    color: var(--sleek-text-muted);
                    text-decoration: none;
                    font-size: 0.9rem;
                    font-weight: 500;
                    border-radius: var(--sleek-radius-xs);
                    transition: var(--sleek-transition);
                }

                .sleek-nav-link:hover {
                    color: var(--sleek-text);
                    background: rgba(255,255,255,0.05);
                }

                .sleek-header-actions {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }

                .sleek-cart-btn {
                    position: relative;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 44px;
                    height: 44px;
                    border-radius: 50%;
                    background: rgba(255,255,255,0.05);
                    border: 1px solid var(--sleek-border);
                    color: var(--sleek-text);
                    text-decoration: none;
                    transition: var(--sleek-transition);
                }

                .sleek-cart-btn:hover {
                    background: rgba(255,255,255,0.1);
                    border-color: var(--sleek-border-light);
                    transform: scale(1.05);
                }

                .sleek-cart-badge {
                    position: absolute;
                    top: -4px;
                    right: -4px;
                    min-width: 20px;
                    height: 20px;
                    padding: 0 6px;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    color: white;
                    font-size: 0.7rem;
                    font-weight: 700;
                    border-radius: 10px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 8px var(--sleek-glow);
                }

                .sleek-account-btn {
                    display: none;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.625rem 1.25rem;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    color: white;
                    text-decoration: none;
                    font-size: 0.875rem;
                    font-weight: 600;
                    border-radius: 50px;
                    transition: var(--sleek-transition);
                    box-shadow: 0 4px 15px var(--sleek-glow);
                }

                @media (min-width: 768px) {
                    .sleek-account-btn {
                        display: flex;
                    }
                }

                .sleek-account-btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px var(--sleek-glow);
                }

                /* Mobile Menu Button */
                .sleek-menu-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 44px;
                    height: 44px;
                    border: none;
                    background: transparent;
                    color: var(--sleek-text);
                    cursor: pointer;
                    border-radius: var(--sleek-radius-xs);
                    transition: var(--sleek-transition);
                }

                @media (min-width: 768px) {
                    .sleek-menu-btn {
                        display: none;
                    }
                }

                .sleek-menu-btn:hover {
                    background: rgba(255,255,255,0.05);
                }

                /* ========================================
                   MOBILE DRAWER - App-like Navigation
                   Uses Tailwind classes for App.ts compatibility
                   ======================================== */
                .sleek-drawer-overlay {
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.6);
                    backdrop-filter: blur(4px);
                    z-index: 200;
                    transition: opacity 0.3s ease;
                }

                .sleek-drawer-overlay.opacity-0 {
                    opacity: 0;
                }

                .sleek-drawer-overlay.hidden {
                    display: none;
                }

                .sleek-drawer {
                    position: fixed;
                    top: 0;
                    right: 0;
                    bottom: 0;
                    width: 300px;
                    max-width: 85vw;
                    background: var(--sleek-surface);
                    z-index: 201;
                    transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1);
                    display: flex;
                    flex-direction: column;
                    border-left: 1px solid var(--sleek-border);
                }

                .sleek-drawer.translate-x-full {
                    transform: translateX(100%);
                }

                .sleek-drawer-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 1rem 1.25rem;
                    border-bottom: 1px solid var(--sleek-border);
                }

                .sleek-drawer-title {
                    font-weight: 600;
                    font-size: 1.1rem;
                    color: var(--sleek-text);
                }

                .sleek-drawer-close {
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(255,255,255,0.05);
                    border: none;
                    border-radius: 50%;
                    color: var(--sleek-text-muted);
                    cursor: pointer;
                    transition: var(--sleek-transition);
                }

                .sleek-drawer-close:hover {
                    background: rgba(255,255,255,0.1);
                    color: var(--sleek-text);
                }

                .sleek-drawer-nav {
                    flex: 1;
                    padding: 1rem;
                    overflow-y: auto;
                }

                .sleek-drawer-link {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    padding: 0.875rem 1rem;
                    color: var(--sleek-text);
                    text-decoration: none;
                    font-weight: 500;
                    border-radius: var(--sleek-radius-sm);
                    transition: var(--sleek-transition);
                    margin-bottom: 0.25rem;
                }

                .sleek-drawer-link:hover,
                .sleek-drawer-link:active {
                    background: rgba(255,255,255,0.05);
                }

                .sleek-drawer-link svg {
                    width: 20px;
                    height: 20px;
                    color: var(--sleek-text-muted);
                }

                .sleek-drawer-divider {
                    height: 1px;
                    background: var(--sleek-border);
                    margin: 1rem 0;
                }

                .sleek-drawer-footer {
                    padding: 1rem;
                    border-top: 1px solid var(--sleek-border);
                }

                .sleek-drawer-cta {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    width: 100%;
                    padding: 0.875rem;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    color: white;
                    text-decoration: none;
                    font-weight: 600;
                    border-radius: var(--sleek-radius-sm);
                    transition: var(--sleek-transition);
                }

                /* ========================================
                   BUTTONS - Premium Interactive Elements
                   ======================================== */
                .sleek-btn-primary {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    padding: 0.875rem 1.75rem;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end));
                    color: white;
                    font-weight: 600;
                    font-size: 0.9rem;
                    border: none;
                    border-radius: 50px;
                    cursor: pointer;
                    transition: var(--sleek-transition);
                    box-shadow: 0 4px 15px var(--sleek-glow);
                    text-decoration: none;
                }

                .sleek-btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 8px 25px var(--sleek-glow);
                }

                .sleek-btn-primary:active {
                    transform: translateY(0);
                }

                .sleek-btn-secondary {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 0.5rem;
                    padding: 0.875rem 1.75rem;
                    background: transparent;
                    color: var(--sleek-text);
                    font-weight: 600;
                    font-size: 0.9rem;
                    border: 1px solid var(--sleek-border-light);
                    border-radius: 50px;
                    cursor: pointer;
                    transition: var(--sleek-transition);
                    text-decoration: none;
                }

                .sleek-btn-secondary:hover {
                    background: rgba(255,255,255,0.05);
                    border-color: rgba(255,255,255,0.2);
                }

                /* ========================================
                   CARDS - Elevated Surfaces
                   ======================================== */
                .sleek-card {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    overflow: hidden;
                    transition: var(--sleek-transition);
                }

                .sleek-card-hover:hover {
                    border-color: var(--sleek-border-light);
                    transform: translateY(-4px);
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                }

                .tixello-card {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    padding: 1.25rem;
                    transition: var(--sleek-transition);
                }

                .tixello-card:hover {
                    border-color: var(--sleek-border-light);
                }

                /* ========================================
                   TYPOGRAPHY
                   ======================================== */
                .sleek-heading {
                    font-size: 1.75rem;
                    font-weight: 700;
                    color: var(--sleek-text);
                    letter-spacing: -0.02em;
                    line-height: 1.2;
                }

                .sleek-subheading {
                    font-size: 1rem;
                    color: var(--sleek-text-muted);
                    line-height: 1.6;
                }

                /* ========================================
                   MAIN CONTENT AREA
                   ======================================== */
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

                /* ========================================
                   MOBILE BOTTOM NAVIGATION (App-like)
                   ======================================== */
                .sleek-bottom-nav {
                    display: flex;
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    z-index: 90;
                    background: rgba(18, 18, 26, 0.95);
                    backdrop-filter: blur(20px);
                    border-top: 1px solid var(--sleek-border);
                    padding: 0.5rem 0;
                    padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
                }

                @media (min-width: 768px) {
                    .sleek-bottom-nav {
                        display: none;
                    }
                }

                .sleek-bottom-nav-item {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 0.25rem;
                    padding: 0.5rem;
                    color: var(--sleek-text-muted);
                    text-decoration: none;
                    font-size: 0.65rem;
                    font-weight: 500;
                    transition: var(--sleek-transition);
                }

                .sleek-bottom-nav-item.active,
                .sleek-bottom-nav-item:hover {
                    color: var(--sleek-gradient-start);
                }

                .sleek-bottom-nav-item svg {
                    width: 24px;
                    height: 24px;
                }

                .sleek-bottom-nav-item .nav-badge {
                    position: absolute;
                    top: 2px;
                    right: calc(50% - 18px);
                    min-width: 16px;
                    height: 16px;
                    background: var(--sleek-gradient-start);
                    color: white;
                    font-size: 0.6rem;
                    font-weight: 700;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 4px;
                }

                /* Add bottom padding for mobile nav */
                @media (max-width: 767px) {
                    main, #tixello-content {
                        padding-bottom: 80px;
                    }
                }

                /* ========================================
                   FORM INPUTS - Modern Style
                   ======================================== */
                input[type="text"],
                input[type="email"],
                input[type="password"],
                input[type="tel"],
                input[type="number"],
                select,
                textarea {
                    width: 100%;
                    padding: 0.875rem 1rem;
                    background: var(--sleek-surface-elevated);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius-sm);
                    color: var(--sleek-text);
                    font-size: 1rem;
                    transition: var(--sleek-transition);
                }

                input:focus,
                select:focus,
                textarea:focus {
                    outline: none;
                    border-color: var(--sleek-gradient-start);
                    box-shadow: 0 0 0 3px var(--sleek-glow);
                }

                input::placeholder,
                textarea::placeholder {
                    color: var(--sleek-text-subtle);
                }

                label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-weight: 500;
                    color: var(--sleek-text);
                    font-size: 0.9rem;
                }

                /* ========================================
                   UTILITY CLASSES
                   ======================================== */
                .text-primary { color: var(--sleek-gradient-start); }
                .text-success { color: var(--sleek-success); }
                .text-warning { color: var(--sleek-warning); }
                .text-error { color: var(--sleek-error); }
                .text-muted { color: var(--sleek-text-muted); }

                .bg-surface { background: var(--sleek-surface); }
                .bg-elevated { background: var(--sleek-surface-elevated); }

                /* Status Badges */
                .sleek-badge {
                    display: inline-flex;
                    align-items: center;
                    padding: 0.375rem 0.75rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    border-radius: 50px;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .sleek-badge-success {
                    background: rgba(16, 185, 129, 0.15);
                    color: var(--sleek-success);
                }

                .sleek-badge-warning {
                    background: rgba(245, 158, 11, 0.15);
                    color: var(--sleek-warning);
                }

                .sleek-badge-error {
                    background: rgba(239, 68, 68, 0.15);
                    color: var(--sleek-error);
                }

                .sleek-badge-info {
                    background: var(--sleek-glow);
                    color: var(--sleek-gradient-start);
                }

                /* Animations */
                @keyframes sleek-fade-in {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @keyframes sleek-pulse {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.6; }
                }

                .sleek-animate-in {
                    animation: sleek-fade-in 0.4s ease-out;
                }

                /* Scrollbar styling */
                ::-webkit-scrollbar {
                    width: 8px;
                    height: 8px;
                }

                ::-webkit-scrollbar-track {
                    background: var(--sleek-bg);
                }

                ::-webkit-scrollbar-thumb {
                    background: var(--sleek-border-light);
                    border-radius: 4px;
                }

                ::-webkit-scrollbar-thumb:hover {
                    background: rgba(255,255,255,0.2);
                }

                /* ========================================
                   PAGE STYLES - HOME PAGE
                   ======================================== */
                .max-w-7xl {
                    max-width: 1400px;
                }

                /* Override Tailwind grays for dark mode */
                .text-gray-900 { color: var(--sleek-text) !important; }
                .text-gray-800 { color: var(--sleek-text) !important; }
                .text-gray-700 { color: rgba(255,255,255,0.9) !important; }
                .text-gray-600 { color: var(--sleek-text-muted) !important; }
                .text-gray-500 { color: var(--sleek-text-muted) !important; }
                .text-gray-400 { color: var(--sleek-text-subtle) !important; }

                .bg-gray-50 { background: var(--sleek-bg) !important; }
                .bg-gray-100 { background: var(--sleek-surface) !important; }
                .bg-gray-200 { background: var(--sleek-surface-elevated) !important; }
                .bg-white { background: var(--sleek-surface) !important; }

                .border-gray-100 { border-color: var(--sleek-border) !important; }
                .border-gray-200 { border-color: var(--sleek-border) !important; }
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

                .hover\\:bg-primary-dark:hover {
                    background: linear-gradient(135deg, var(--sleek-gradient-end), var(--sleek-gradient-start)) !important;
                }

                /* Home page hero */
                #tixello-content > div:first-child {
                    background: var(--sleek-bg);
                }

                /* ========================================
                   PAGE STYLES - EVENTS PAGE
                   ======================================== */
                #events-calendar {
                    background: var(--sleek-bg) !important;
                    border-color: var(--sleek-border) !important;
                }

                .days-scroller {
                    scrollbar-width: none;
                }

                .days-scroller::-webkit-scrollbar {
                    display: none;
                }

                /* Day buttons in calendar */
                #days-scroller button,
                .day-btn {
                    background: var(--sleek-surface) !important;
                    border-color: var(--sleek-border) !important;
                    color: var(--sleek-text-muted) !important;
                }

                #days-scroller button:hover,
                .day-btn:hover {
                    border-color: var(--sleek-border-light) !important;
                    background: var(--sleek-surface-elevated) !important;
                }

                /* Selected day */
                .day-btn.border-gray-900,
                [class*="border-gray-900"] {
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                    border-color: var(--sleek-gradient-start) !important;
                    color: white !important;
                }

                /* Days with events */
                .border-emerald-300 {
                    border-color: rgba(16, 185, 129, 0.5) !important;
                    background: rgba(16, 185, 129, 0.1) !important;
                }

                .border-amber-300 {
                    border-color: rgba(245, 158, 11, 0.5) !important;
                    background: rgba(245, 158, 11, 0.1) !important;
                }

                .border-red-300 {
                    border-color: rgba(239, 68, 68, 0.5) !important;
                    background: rgba(239, 68, 68, 0.1) !important;
                }

                /* Month buttons */
                .month-btn {
                    background: var(--sleek-surface) !important;
                    border-color: var(--sleek-border) !important;
                    color: var(--sleek-text-muted) !important;
                }

                .month-btn:hover {
                    border-color: var(--sleek-border-light) !important;
                }

                .month-btn.bg-gray-900 {
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                    border-color: var(--sleek-gradient-start) !important;
                    color: white !important;
                }

                /* Event cards in grid */
                #events-container a,
                .event-card {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                    transition: var(--sleek-transition) !important;
                }

                #events-container a:hover,
                .event-card:hover {
                    border-color: var(--sleek-border-light) !important;
                    transform: translateY(-4px) !important;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.3) !important;
                }

                /* Event card badges */
                .bg-red-100, .bg-amber-100, .bg-gray-200, .bg-blue-50 {
                    background: rgba(255,255,255,0.1) !important;
                }

                .text-red-700 { color: #ef4444 !important; }
                .text-amber-700 { color: #f59e0b !important; }
                .text-blue-700 { color: #3b82f6 !important; }

                /* ========================================
                   PAGE STYLES - EVENT DETAIL PAGE
                   ======================================== */
                .event-detail-page,
                [class*="event-detail"] {
                    background: var(--sleek-bg) !important;
                }

                /* Ticket type cards */
                .ticket-type-card,
                [class*="ticket-selector"] {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                }

                .ticket-type-card:hover {
                    border-color: var(--sleek-gradient-start) !important;
                }

                /* Quantity buttons */
                .qty-btn,
                button[class*="qty"] {
                    background: var(--sleek-surface-elevated) !important;
                    border: 1px solid var(--sleek-border) !important;
                    color: var(--sleek-text) !important;
                    border-radius: var(--sleek-radius-xs) !important;
                }

                /* ========================================
                   PAGE STYLES - CART PAGE
                   ======================================== */
                .cart-item,
                [class*="cart-item"] {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                    padding: 1rem !important;
                    margin-bottom: 1rem !important;
                }

                .cart-summary,
                [class*="cart-summary"],
                [class*="order-summary"] {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                    padding: 1.5rem !important;
                }

                /* ========================================
                   PAGE STYLES - CHECKOUT PAGE
                   ======================================== */
                .checkout-form,
                [class*="checkout"] form {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                    padding: 1.5rem !important;
                }

                /* Stripe elements container */
                #payment-element,
                .StripeElement,
                [class*="stripe"] {
                    background: var(--sleek-surface-elevated) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius-sm) !important;
                    padding: 1rem !important;
                }

                /* ========================================
                   PAGE STYLES - BLOG
                   ======================================== */
                .blog-page,
                .blog-card,
                .blog-article {
                    background: transparent !important;
                }

                .blog-card {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                }

                .blog-card:hover {
                    border-color: var(--sleek-border-light) !important;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
                }

                /* Category filter buttons */
                .category-filter {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    color: var(--sleek-text-muted) !important;
                }

                .category-filter:hover {
                    border-color: var(--sleek-border-light) !important;
                }

                .category-filter.bg-primary-600 {
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                    border-color: var(--sleek-gradient-start) !important;
                    color: white !important;
                }

                /* Blog content prose */
                .prose, .blog-content {
                    color: var(--sleek-text-muted) !important;
                }

                .prose h1, .prose h2, .prose h3, .prose h4,
                .blog-content h1, .blog-content h2, .blog-content h3 {
                    color: var(--sleek-text) !important;
                }

                .prose a, .blog-content a {
                    color: var(--sleek-gradient-start) !important;
                }

                .prose code, .blog-content code {
                    background: var(--sleek-surface-elevated) !important;
                    color: var(--sleek-gradient-start) !important;
                }

                .prose pre, .blog-content pre {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                }

                /* Pagination */
                .blog-pagination button,
                .page-btn {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    color: var(--sleek-text-muted) !important;
                }

                .blog-pagination button:hover,
                .page-btn:hover {
                    border-color: var(--sleek-border-light) !important;
                    background: var(--sleek-surface-elevated) !important;
                }

                /* ========================================
                   PAGE STYLES - ADMIN DASHBOARD
                   ======================================== */
                .admin-sidebar,
                [class*="admin-sidebar"] {
                    background: var(--sleek-surface) !important;
                    border-right: 1px solid var(--sleek-border) !important;
                }

                .admin-content,
                [class*="admin-content"] {
                    background: var(--sleek-bg) !important;
                }

                /* Admin stat cards */
                #admin-stats .tixello-card,
                .admin-stat-card {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                }

                /* Admin tables */
                table {
                    width: 100%;
                    border-collapse: separate;
                    border-spacing: 0;
                }

                th {
                    background: var(--sleek-surface-elevated) !important;
                    color: var(--sleek-text-muted) !important;
                    font-weight: 600 !important;
                    text-transform: uppercase !important;
                    font-size: 0.75rem !important;
                    letter-spacing: 0.05em !important;
                    padding: 1rem !important;
                    text-align: left !important;
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

                /* Admin buttons */
                .tixello-btn,
                button.tixello-btn {
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 0.5rem !important;
                    padding: 0.75rem 1.25rem !important;
                    background: linear-gradient(135deg, var(--sleek-gradient-start), var(--sleek-gradient-end)) !important;
                    color: white !important;
                    font-weight: 600 !important;
                    border: none !important;
                    border-radius: var(--sleek-radius-sm) !important;
                    cursor: pointer !important;
                    transition: var(--sleek-transition) !important;
                    box-shadow: 0 4px 15px var(--sleek-glow) !important;
                }

                .tixello-btn:hover {
                    transform: translateY(-2px) !important;
                    box-shadow: 0 8px 25px var(--sleek-glow) !important;
                }

                .tixello-btn-secondary {
                    background: transparent !important;
                    border: 1px solid var(--sleek-border-light) !important;
                    color: var(--sleek-text) !important;
                    box-shadow: none !important;
                }

                /* ========================================
                   PAGE STYLES - ACCOUNT PAGES
                   ======================================== */
                .tixello-account,
                [class*="account-page"] {
                    background: var(--sleek-bg) !important;
                    min-height: calc(100vh - 140px) !important;
                }

                /* Account cards */
                .account-card,
                [class*="account-card"] {
                    background: var(--sleek-surface) !important;
                    border: 1px solid var(--sleek-border) !important;
                    border-radius: var(--sleek-radius) !important;
                }

                /* ========================================
                   PAGE STYLES - LOADING & EMPTY STATES
                   ======================================== */
                .animate-pulse {
                    background: var(--sleek-surface-elevated) !important;
                    animation: sleek-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite !important;
                }

                /* Empty state styling */
                .empty-state,
                [class*="empty-state"] {
                    text-align: center !important;
                    padding: 4rem 2rem !important;
                }

                /* ========================================
                   PAGE STYLES - SUCCESS/ERROR MESSAGES
                   ======================================== */
                .text-red-500, .text-red-600 {
                    color: var(--sleek-error) !important;
                }

                .text-green-500, .text-green-600 {
                    color: var(--sleek-success) !important;
                }

                .bg-green-100, .bg-green-50 {
                    background: rgba(16, 185, 129, 0.15) !important;
                }

                .bg-red-100, .bg-red-50 {
                    background: rgba(239, 68, 68, 0.15) !important;
                }

                /* ========================================
                   RESPONSIVE UTILITIES
                   ======================================== */
                @media (max-width: 767px) {
                    .hidden-mobile {
                        display: none !important;
                    }

                    /* Stack cards on mobile */
                    .grid {
                        gap: 1rem !important;
                    }

                    /* Smaller padding on mobile */
                    .px-4 { padding-left: 1rem !important; padding-right: 1rem !important; }
                    .py-8 { padding-top: 1.5rem !important; padding-bottom: 1.5rem !important; }
                    .py-12 { padding-top: 2rem !important; padding-bottom: 2rem !important; }
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
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    padding: 1rem 1.25rem;
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius-sm);
                    box-shadow: 0 10px 40px rgba(0,0,0,0.4);
                    pointer-events: auto;
                    animation: sleek-toast-in 0.4s cubic-bezier(0.32, 0.72, 0, 1);
                    max-width: 400px;
                }

                .sleek-toast.removing {
                    animation: sleek-toast-out 0.3s ease-in forwards;
                }

                @keyframes sleek-toast-in {
                    from {
                        opacity: 0;
                        transform: translateX(100%);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }

                @keyframes sleek-toast-out {
                    to {
                        opacity: 0;
                        transform: translateX(100%);
                    }
                }

                @media (max-width: 767px) {
                    @keyframes sleek-toast-in {
                        from {
                            opacity: 0;
                            transform: translateY(100%);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    @keyframes sleek-toast-out {
                        to {
                            opacity: 0;
                            transform: translateY(100%);
                        }
                    }
                }

                .sleek-toast-icon {
                    width: 24px;
                    height: 24px;
                    flex-shrink: 0;
                }

                .sleek-toast-content {
                    flex: 1;
                    min-width: 0;
                }

                .sleek-toast-title {
                    font-weight: 600;
                    font-size: 0.9rem;
                    color: var(--sleek-text);
                    margin-bottom: 0.125rem;
                }

                .sleek-toast-message {
                    font-size: 0.85rem;
                    color: var(--sleek-text-muted);
                }

                .sleek-toast-close {
                    width: 28px;
                    height: 28px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: transparent;
                    border: none;
                    color: var(--sleek-text-subtle);
                    cursor: pointer;
                    border-radius: 50%;
                    transition: var(--sleek-transition);
                    flex-shrink: 0;
                }

                .sleek-toast-close:hover {
                    background: rgba(255,255,255,0.1);
                    color: var(--sleek-text);
                }

                .sleek-toast.success {
                    border-color: rgba(16, 185, 129, 0.3);
                }

                .sleek-toast.success .sleek-toast-icon {
                    color: var(--sleek-success);
                }

                .sleek-toast.error {
                    border-color: rgba(239, 68, 68, 0.3);
                }

                .sleek-toast.error .sleek-toast-icon {
                    color: var(--sleek-error);
                }

                .sleek-toast.warning {
                    border-color: rgba(245, 158, 11, 0.3);
                }

                .sleek-toast.warning .sleek-toast-icon {
                    color: var(--sleek-warning);
                }

                .sleek-toast.info {
                    border-color: rgba(99, 102, 241, 0.3);
                }

                .sleek-toast.info .sleek-toast-icon {
                    color: var(--sleek-gradient-start);
                }

                /* ========================================
                   MODAL DIALOGS
                   ======================================== */
                .sleek-modal-overlay {
                    position: fixed;
                    inset: 0;
                    background: rgba(0,0,0,0.7);
                    backdrop-filter: blur(8px);
                    z-index: 500;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 1rem;
                    opacity: 0;
                    visibility: hidden;
                    transition: opacity 0.3s ease, visibility 0.3s ease;
                }

                .sleek-modal-overlay.active {
                    opacity: 1;
                    visibility: visible;
                }

                .sleek-modal {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    width: 100%;
                    max-width: 500px;
                    max-height: 90vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                    transform: scale(0.95) translateY(10px);
                    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
                }

                .sleek-modal-overlay.active .sleek-modal {
                    transform: scale(1) translateY(0);
                }

                .sleek-modal-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 1.25rem 1.5rem;
                    border-bottom: 1px solid var(--sleek-border);
                }

                .sleek-modal-title {
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: var(--sleek-text);
                }

                .sleek-modal-close {
                    width: 36px;
                    height: 36px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: rgba(255,255,255,0.05);
                    border: none;
                    border-radius: 50%;
                    color: var(--sleek-text-muted);
                    cursor: pointer;
                    transition: var(--sleek-transition);
                }

                .sleek-modal-close:hover {
                    background: rgba(255,255,255,0.1);
                    color: var(--sleek-text);
                }

                .sleek-modal-body {
                    padding: 1.5rem;
                    overflow-y: auto;
                    flex: 1;
                }

                .sleek-modal-footer {
                    display: flex;
                    gap: 0.75rem;
                    padding: 1.25rem 1.5rem;
                    border-top: 1px solid var(--sleek-border);
                    justify-content: flex-end;
                }

                /* Ticket Modal Specific */
                .sleek-ticket-modal {
                    max-width: 420px;
                    text-align: center;
                }

                .sleek-ticket-qr {
                    width: 200px;
                    height: 200px;
                    margin: 1.5rem auto;
                    background: white;
                    border-radius: var(--sleek-radius-sm);
                    padding: 1rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .sleek-ticket-qr img, .sleek-ticket-qr canvas {
                    max-width: 100%;
                    max-height: 100%;
                }

                .sleek-ticket-info {
                    margin-top: 1rem;
                }

                .sleek-ticket-number {
                    font-family: monospace;
                    font-size: 1.1rem;
                    color: var(--sleek-text);
                    background: var(--sleek-surface-elevated);
                    padding: 0.5rem 1rem;
                    border-radius: var(--sleek-radius-xs);
                    display: inline-block;
                }

                /* ========================================
                   SKELETON LOADING
                   ======================================== */
                .sleek-skeleton {
                    background: linear-gradient(
                        90deg,
                        var(--sleek-surface-elevated) 0%,
                        rgba(255,255,255,0.05) 50%,
                        var(--sleek-surface-elevated) 100%
                    );
                    background-size: 200% 100%;
                    animation: sleek-skeleton-shimmer 1.5s infinite;
                    border-radius: var(--sleek-radius-xs);
                }

                @keyframes sleek-skeleton-shimmer {
                    0% {
                        background-position: 200% 0;
                    }
                    100% {
                        background-position: -200% 0;
                    }
                }

                .sleek-skeleton-text {
                    height: 1em;
                    margin-bottom: 0.5em;
                }

                .sleek-skeleton-text:last-child {
                    width: 70%;
                }

                .sleek-skeleton-title {
                    height: 1.5em;
                    width: 60%;
                    margin-bottom: 1rem;
                }

                .sleek-skeleton-image {
                    aspect-ratio: 16/9;
                    width: 100%;
                }

                .sleek-skeleton-avatar {
                    width: 48px;
                    height: 48px;
                    border-radius: 50%;
                }

                .sleek-skeleton-button {
                    height: 44px;
                    width: 120px;
                    border-radius: 50px;
                }

                .sleek-skeleton-card {
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius);
                    padding: 1rem;
                    overflow: hidden;
                }

                .sleek-skeleton-card .sleek-skeleton-image {
                    margin: -1rem -1rem 1rem -1rem;
                    border-radius: 0;
                }

                /* ========================================
                   SEARCH BAR
                   ======================================== */
                .sleek-search {
                    position: relative;
                    display: none;
                }

                @media (min-width: 1024px) {
                    .sleek-search {
                        display: block;
                    }
                }

                .sleek-search-input {
                    width: 220px;
                    padding: 0.625rem 1rem 0.625rem 2.5rem;
                    background: rgba(255,255,255,0.05);
                    border: 1px solid var(--sleek-border);
                    border-radius: 50px;
                    color: var(--sleek-text);
                    font-size: 0.875rem;
                    transition: var(--sleek-transition);
                }

                .sleek-search-input:focus {
                    outline: none;
                    width: 280px;
                    background: var(--sleek-surface-elevated);
                    border-color: var(--sleek-gradient-start);
                    box-shadow: 0 0 0 3px var(--sleek-glow);
                }

                .sleek-search-input::placeholder {
                    color: var(--sleek-text-subtle);
                }

                .sleek-search-icon {
                    position: absolute;
                    left: 0.875rem;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 16px;
                    height: 16px;
                    color: var(--sleek-text-subtle);
                    pointer-events: none;
                }

                .sleek-search-mobile-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 44px;
                    height: 44px;
                    background: transparent;
                    border: none;
                    color: var(--sleek-text-muted);
                    cursor: pointer;
                    border-radius: var(--sleek-radius-xs);
                    transition: var(--sleek-transition);
                }

                @media (min-width: 1024px) {
                    .sleek-search-mobile-btn {
                        display: none;
                    }
                }

                .sleek-search-mobile-btn:hover {
                    background: rgba(255,255,255,0.05);
                    color: var(--sleek-text);
                }

                /* Mobile Search Overlay */
                .sleek-search-overlay {
                    position: fixed;
                    inset: 0;
                    background: var(--sleek-bg);
                    z-index: 300;
                    padding: 1rem;
                    transform: translateY(-100%);
                    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
                }

                .sleek-search-overlay.active {
                    transform: translateY(0);
                }

                .sleek-search-overlay-header {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-bottom: 1rem;
                }

                .sleek-search-overlay-input {
                    flex: 1;
                    padding: 0.875rem 1rem;
                    background: var(--sleek-surface);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius-sm);
                    color: var(--sleek-text);
                    font-size: 1rem;
                }

                .sleek-search-overlay-input:focus {
                    outline: none;
                    border-color: var(--sleek-gradient-start);
                }

                .sleek-search-cancel {
                    padding: 0.5rem 1rem;
                    background: transparent;
                    border: none;
                    color: var(--sleek-gradient-start);
                    font-weight: 500;
                    cursor: pointer;
                }

                /* ========================================
                   QUANTITY SELECTORS (Improved)
                   ======================================== */
                .sleek-qty-selector {
                    display: inline-flex;
                    align-items: center;
                    background: var(--sleek-surface-elevated);
                    border: 1px solid var(--sleek-border);
                    border-radius: var(--sleek-radius-sm);
                    overflow: hidden;
                }

                .sleek-qty-btn {
                    width: 44px;
                    height: 44px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: transparent;
                    border: none;
                    color: var(--sleek-text);
                    cursor: pointer;
                    transition: var(--sleek-transition);
                    font-size: 1.25rem;
                    font-weight: 500;
                }

                .sleek-qty-btn:hover:not(:disabled) {
                    background: rgba(255,255,255,0.1);
                }

                .sleek-qty-btn:active:not(:disabled) {
                    background: rgba(255,255,255,0.15);
                }

                .sleek-qty-btn:disabled {
                    opacity: 0.3;
                    cursor: not-allowed;
                }

                .sleek-qty-value {
                    min-width: 48px;
                    text-align: center;
                    font-weight: 600;
                    font-size: 1rem;
                    color: var(--sleek-text);
                    border-left: 1px solid var(--sleek-border);
                    border-right: 1px solid var(--sleek-border);
                    padding: 0 0.5rem;
                }

                /* Override default quantity buttons */
                .ticket-plus, .ticket-minus,
                .cart-qty-plus, .cart-qty-minus,
                .mobile-ticket-plus, .mobile-ticket-minus,
                button[class*="qty"] {
                    min-width: 40px !important;
                    min-height: 40px !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    background: var(--sleek-surface-elevated) !important;
                    border: 1px solid var(--sleek-border) !important;
                    color: var(--sleek-text) !important;
                    border-radius: var(--sleek-radius-xs) !important;
                    font-size: 1.25rem !important;
                    font-weight: 500 !important;
                    cursor: pointer !important;
                    transition: var(--sleek-transition) !important;
                }

                .ticket-plus:hover, .ticket-minus:hover,
                .cart-qty-plus:hover, .cart-qty-minus:hover,
                .mobile-ticket-plus:hover, .mobile-ticket-minus:hover,
                button[class*="qty"]:hover {
                    background: rgba(255,255,255,0.1) !important;
                    border-color: var(--sleek-border-light) !important;
                }

                .ticket-qty-display, .mobile-ticket-qty {
                    min-width: 40px !important;
                    text-align: center !important;
                    font-weight: 600 !important;
                    font-size: 1rem !important;
                    color: var(--sleek-text) !important;
                }

                /* ========================================
                   PAGE TRANSITIONS
                   ======================================== */
                #tixello-content {
                    animation: sleek-page-in 0.4s ease-out;
                }

                @keyframes sleek-page-in {
                    from {
                        opacity: 0;
                        transform: translateY(10px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }

                .sleek-page-transition {
                    animation: sleek-page-out 0.2s ease-in forwards;
                }

                @keyframes sleek-page-out {
                    to {
                        opacity: 0;
                        transform: translateY(-10px);
                    }
                }

                /* Staggered animations for lists */
                .sleek-stagger > * {
                    opacity: 0;
                    animation: sleek-fade-in 0.4s ease-out forwards;
                }

                .sleek-stagger > *:nth-child(1) { animation-delay: 0.05s; }
                .sleek-stagger > *:nth-child(2) { animation-delay: 0.1s; }
                .sleek-stagger > *:nth-child(3) { animation-delay: 0.15s; }
                .sleek-stagger > *:nth-child(4) { animation-delay: 0.2s; }
                .sleek-stagger > *:nth-child(5) { animation-delay: 0.25s; }
                .sleek-stagger > *:nth-child(6) { animation-delay: 0.3s; }
                .sleek-stagger > *:nth-child(7) { animation-delay: 0.35s; }
                .sleek-stagger > *:nth-child(8) { animation-delay: 0.4s; }

                /* ========================================
                   PRINT STYLES
                   ======================================== */
                @media print {
                    /* Hide navigation and non-essential elements */
                    .sleek-header,
                    .sleek-footer,
                    .sleek-bottom-nav,
                    .sleek-drawer-overlay,
                    .sleek-drawer,
                    .sleek-toast-container,
                    .sleek-modal-overlay,
                    .sleek-search-overlay,
                    button,
                    .sleek-btn-primary,
                    .sleek-btn-secondary {
                        display: none !important;
                    }

                    /* Reset dark theme for print */
                    body, #tixello-app, main, #tixello-content {
                        background: white !important;
                        color: black !important;
                        padding: 0 !important;
                        margin: 0 !important;
                    }

                    /* Ticket print styles */
                    .sleek-ticket-print {
                        page-break-inside: avoid;
                        border: 2px solid #000 !important;
                        padding: 2rem !important;
                        margin: 1rem 0 !important;
                        background: white !important;
                    }

                    .sleek-ticket-print * {
                        color: black !important;
                    }

                    .sleek-ticket-print .sleek-ticket-qr {
                        width: 150px !important;
                        height: 150px !important;
                        margin: 1rem auto !important;
                        border: 1px solid #ccc !important;
                    }

                    .sleek-ticket-print h2, .sleek-ticket-print h3 {
                        margin-bottom: 0.5rem !important;
                    }

                    .sleek-ticket-print .ticket-details {
                        margin-top: 1rem;
                        border-top: 1px dashed #ccc;
                        padding-top: 1rem;
                    }

                    /* Order print styles */
                    .order-print {
                        page-break-inside: avoid;
                    }

                    /* Ensure QR codes print properly */
                    canvas, img {
                        max-width: 100% !important;
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }

                    /* Hide decorative elements */
                    .sleek-badge,
                    .animate-pulse,
                    .sleek-skeleton {
                        display: none !important;
                    }

                    /* Show URLs after links */
                    a[href]:after {
                        content: " (" attr(href) ")";
                        font-size: 0.8em;
                        color: #666;
                    }

                    a[href^="#"]:after,
                    a[href^="javascript"]:after {
                        content: "";
                    }
                }

                /* ========================================
                   ADDITIONAL REFINEMENTS
                   ======================================== */
                /* Smooth scrolling */
                html {
                    scroll-behavior: smooth;
                }

                @media (prefers-reduced-motion: reduce) {
                    html {
                        scroll-behavior: auto;
                    }

                    *, *::before, *::after {
                        animation-duration: 0.01ms !important;
                        animation-iteration-count: 1 !important;
                        transition-duration: 0.01ms !important;
                    }
                }

                /* Focus visible for accessibility */
                *:focus-visible {
                    outline: 2px solid var(--sleek-gradient-start);
                    outline-offset: 2px;
                }

                /* Selection color */
                ::selection {
                    background: var(--sleek-gradient-start);
                    color: white;
                }

                /* Tap highlight color for mobile */
                * {
                    -webkit-tap-highlight-color: rgba(99, 102, 241, 0.2);
                }

                /* PWA safe area support */
                @supports (padding: env(safe-area-inset-bottom)) {
                    .sleek-bottom-nav {
                        padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
                    }

                    .sleek-footer {
                        padding-bottom: calc(1rem + env(safe-area-inset-bottom));
                    }
                }

                /* Image loading placeholder */
                img {
                    background: var(--sleek-surface-elevated);
                }

                img[src] {
                    background: transparent;
                }

                /* Better text rendering */
                body {
                    -webkit-font-smoothing: antialiased;
                    -moz-osx-font-smoothing: grayscale;
                    text-rendering: optimizeLegibility;
                }
            </style>

            <!-- Toast Container -->
            <div id="sleek-toast-container" class="sleek-toast-container"></div>

            <header class="sleek-header">
                <div class="sleek-header-inner">
                    <a href="/" class="sleek-logo">
                        ${logo
                            ? `<img src="${logo}" alt="${siteName}">`
                            : `<span>${siteName}</span>`
                        }
                    </a>

                    <!-- Desktop Search -->
                    <div class="sleek-search">
                        <svg class="sleek-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" class="sleek-search-input" placeholder="Cauta evenimente..." id="sleek-search-input">
                    </div>

                    <nav class="sleek-nav">
                        <a href="/events" class="sleek-nav-link">Evenimente</a>
                        <a href="/blog" class="sleek-nav-link">Blog</a>
                        ${menuItemsHtml}
                    </nav>

                    <div class="sleek-header-actions">
                        <!-- Mobile Search Button -->
                        <button class="sleek-search-mobile-btn" id="mobile-search-btn" aria-label="Search">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </button>
                        <a href="/cart" class="sleek-cart-btn" aria-label="Shopping Cart">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span id="cart-badge" class="sleek-cart-badge hidden">0</span>
                        </a>
                        <a href="/account" id="account-link" class="sleek-account-btn">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span>Contul meu</span>
                        </a>
                        <button class="sleek-menu-btn" id="mobile-menu-btn" aria-label="Open Menu">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Mobile Search Overlay -->
            <div id="mobile-search-overlay" class="sleek-search-overlay">
                <div class="sleek-search-overlay-header">
                    <input type="text" class="sleek-search-overlay-input" placeholder="Cauta evenimente..." id="mobile-search-input" autofocus>
                    <button class="sleek-search-cancel" id="mobile-search-cancel">Anuleaza</button>
                </div>
                <div id="mobile-search-results"></div>
            </div>

            <!-- Mobile Drawer -->
            <div id="mobile-menu-overlay" class="sleek-drawer-overlay hidden opacity-0"></div>
            <div id="mobile-menu-drawer" class="sleek-drawer translate-x-full">
                <div class="sleek-drawer-header">
                    <span class="sleek-drawer-title">Menu</span>
                    <button id="mobile-menu-close" class="sleek-drawer-close" aria-label="Close Menu">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <nav class="sleek-drawer-nav">
                    <a href="/events" class="sleek-drawer-link">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Evenimente
                    </a>
                    <a href="/blog" class="sleek-drawer-link">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                        </svg>
                        Blog
                    </a>
                    ${headerMenu.map(item => `
                        <a href="${item.url}" class="sleek-drawer-link">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            ${item.title}
                        </a>
                    `).join('')}
                    <div class="sleek-drawer-divider"></div>
                    <a href="/account" class="sleek-drawer-link">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Contul meu
                    </a>
                    <a href="/account/orders" class="sleek-drawer-link">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Comenzile mele
                    </a>
                    <a href="/account/tickets" class="sleek-drawer-link">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                        </svg>
                        Biletele mele
                    </a>
                </nav>
                <div class="sleek-drawer-footer">
                    <a href="/cart" class="sleek-drawer-cta">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Vezi Cosul
                        <span id="cart-badge-menu" class="sleek-badge sleek-badge-info hidden" style="margin-left: auto;">0</span>
                    </a>
                </div>
            </div>

            <!-- Mobile Bottom Navigation -->
            <nav class="sleek-bottom-nav" id="sleek-bottom-nav">
                <a href="/" class="sleek-bottom-nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Acasa
                </a>
                <a href="/events" class="sleek-bottom-nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Descopera
                </a>
                <a href="/account/tickets" class="sleek-bottom-nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                    </svg>
                    Bilete
                </a>
                <a href="/account" class="sleek-bottom-nav-item">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Cont
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
            socialIcons.push(`<a href="${social.facebook}" target="_blank" class="sleek-social-link" aria-label="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>`);
        }
        if (social.instagram) {
            socialIcons.push(`<a href="${social.instagram}" target="_blank" class="sleek-social-link" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>`);
        }
        if (social.twitter) {
            socialIcons.push(`<a href="${social.twitter}" target="_blank" class="sleek-social-link" aria-label="Twitter"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>`);
        }
        if (social.youtube) {
            socialIcons.push(`<a href="${social.youtube}" target="_blank" class="sleek-social-link" aria-label="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>`);
        }
        if (social.tiktok) {
            socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="sleek-social-link" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>`);
        }
        if (social.linkedin) {
            socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="sleek-social-link" aria-label="LinkedIn"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>`);
        }

        const socialHtml = socialIcons.length > 0
            ? `<div class="sleek-social-icons">${socialIcons.join('')}</div>`
            : '';

        const footerMenuHtml = footerMenu.map(item =>
            `<a href="${item.url}" class="sleek-footer-link">${item.title}</a>`
        ).join('');

        return `
            <style>
                /* ========================================
                   FOOTER - Modern Dark Footer
                   ======================================== */
                .sleek-footer {
                    background: var(--sleek-surface);
                    border-top: 1px solid var(--sleek-border);
                    margin-top: 4rem;
                }

                @media (max-width: 767px) {
                    .sleek-footer {
                        margin-bottom: 64px; /* Space for bottom nav */
                    }
                }

                .sleek-footer-inner {
                    max-width: 1400px;
                    margin: 0 auto;
                    padding: 3rem 1rem;
                }

                @media (min-width: 768px) {
                    .sleek-footer-inner {
                        padding: 4rem 2rem;
                    }
                }

                .sleek-footer-grid {
                    display: grid;
                    grid-template-columns: 1fr;
                    gap: 2rem;
                }

                @media (min-width: 768px) {
                    .sleek-footer-grid {
                        grid-template-columns: 2fr 1fr 1fr 1fr;
                        gap: 3rem;
                    }
                }

                .sleek-footer-brand {
                    max-width: 300px;
                }

                .sleek-footer-logo {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: var(--sleek-text);
                    margin-bottom: 1rem;
                    display: block;
                }

                .sleek-footer-desc {
                    color: var(--sleek-text-muted);
                    font-size: 0.9rem;
                    line-height: 1.7;
                    margin-bottom: 1.5rem;
                }

                .sleek-social-icons {
                    display: flex;
                    gap: 0.75rem;
                }

                .sleek-social-link {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 40px;
                    height: 40px;
                    background: rgba(255,255,255,0.05);
                    border: 1px solid var(--sleek-border);
                    border-radius: 50%;
                    color: var(--sleek-text-muted);
                    transition: var(--sleek-transition);
                }

                .sleek-social-link:hover {
                    background: var(--sleek-gradient-start);
                    border-color: var(--sleek-gradient-start);
                    color: white;
                    transform: translateY(-2px);
                }

                .sleek-footer-col h4 {
                    font-size: 0.875rem;
                    font-weight: 600;
                    color: var(--sleek-text);
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-bottom: 1.25rem;
                }

                .sleek-footer-links {
                    display: flex;
                    flex-direction: column;
                    gap: 0.75rem;
                }

                .sleek-footer-link {
                    color: var(--sleek-text-muted);
                    text-decoration: none;
                    font-size: 0.9rem;
                    transition: var(--sleek-transition);
                }

                .sleek-footer-link:hover {
                    color: var(--sleek-text);
                }

                .sleek-footer-bottom {
                    margin-top: 3rem;
                    padding-top: 2rem;
                    border-top: 1px solid var(--sleek-border);
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                    align-items: center;
                }

                @media (min-width: 768px) {
                    .sleek-footer-bottom {
                        flex-direction: row;
                        justify-content: space-between;
                    }
                }

                .sleek-footer-copyright {
                    color: var(--sleek-text-subtle);
                    font-size: 0.85rem;
                }

                .sleek-footer-powered {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    color: var(--sleek-text-subtle);
                    font-size: 0.85rem;
                }

                .sleek-footer-powered a {
                    color: var(--sleek-gradient-start);
                    text-decoration: none;
                    font-weight: 600;
                    transition: var(--sleek-transition);
                }

                .sleek-footer-powered a:hover {
                    color: var(--sleek-gradient-end);
                }
            </style>

            <footer class="sleek-footer">
                <div class="sleek-footer-inner">
                    <div class="sleek-footer-grid">
                        <div class="sleek-footer-brand">
                            <span class="sleek-footer-logo">${siteName}</span>
                            <p class="sleek-footer-desc">${config.site?.description || 'Descoperiti cele mai bune evenimente si cumparati bilete online.'}</p>
                            ${socialHtml}
                        </div>

                        <div class="sleek-footer-col">
                            <h4>Navigare</h4>
                            <div class="sleek-footer-links">
                                <a href="/events" class="sleek-footer-link">Evenimente</a>
                                <a href="/past-events" class="sleek-footer-link">Evenimente trecute</a>
                                <a href="/blog" class="sleek-footer-link">Blog</a>
                                <a href="/account" class="sleek-footer-link">Contul meu</a>
                            </div>
                        </div>

                        <div class="sleek-footer-col">
                            <h4>Cont</h4>
                            <div class="sleek-footer-links">
                                <a href="/account/orders" class="sleek-footer-link">Comenzile mele</a>
                                <a href="/account/tickets" class="sleek-footer-link">Biletele mele</a>
                                <a href="/account/profile" class="sleek-footer-link">Profil</a>
                            </div>
                        </div>

                        <div class="sleek-footer-col">
                            <h4>Legal</h4>
                            <div class="sleek-footer-links">
                                <a href="/terms" class="sleek-footer-link">Termeni si conditii</a>
                                <a href="/privacy" class="sleek-footer-link">Confidentialitate</a>
                                ${footerMenuHtml}
                            </div>
                        </div>
                    </div>

                    <div class="sleek-footer-bottom">
                        <p class="sleek-footer-copyright">&copy; ${year} ${siteName}. Toate drepturile rezervate.</p>
                        <div class="sleek-footer-powered">
                            <span>Powered by</span>
                            <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank" rel="noopener noreferrer">
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
