import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

/**
 * Theater Template
 *
 * A classic, elegant template designed for theater venues, opera houses,
 * and performing arts centers. Features deep burgundy, gold accents,
 * and sophisticated typography.
 *
 * Design principles:
 * - Hybrid Tailwind/CSS approach (Tailwind for structure, CSS for theming)
 * - Serif fonts for headings (Playfair Display)
 * - Rich burgundy and gold color palette
 * - Elegant, timeless aesthetic
 */

const theaterTemplate: TemplateConfig = {
    name: 'theater',

    // Layout
    headerClass: 'fixed top-0 left-0 right-0 z-50 bg-[#1a0a0d]/95 backdrop-blur-md border-b border-[#D4AF37]/20',
    footerClass: 'bg-[#0f0608] border-t border-[#D4AF37]/20 mt-16',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',

    // Hero
    heroClass: 'text-center py-20 bg-gradient-to-b from-[#1a0a0d] via-[#2d1218] to-[#1a0a0d] text-[#FDF8F0]',
    heroTitleClass: 'text-5xl md:text-6xl font-serif font-bold text-[#FDF8F0] mb-6 tracking-wide',
    heroSubtitleClass: 'text-xl text-[#FDF8F0]/80 mb-10 max-w-2xl mx-auto font-light',

    // Cards
    cardClass: 'bg-[#1a0a0d] border border-[#D4AF37]/20 rounded-lg overflow-hidden shadow-xl transition-all duration-300',
    cardHoverClass: 'hover:border-[#D4AF37]/50 hover:shadow-2xl hover:shadow-[#D4AF37]/10',

    // Buttons
    primaryButtonClass: 'inline-flex items-center gap-2 px-8 py-3 bg-gradient-to-r from-[#D4AF37] to-[#B8962E] text-[#1a0a0d] font-semibold rounded transition-all duration-300 hover:shadow-lg hover:shadow-[#D4AF37]/30 uppercase tracking-wider text-sm',
    secondaryButtonClass: 'inline-flex items-center gap-2 px-8 py-3 border-2 border-[#D4AF37] text-[#D4AF37] font-semibold rounded transition-all duration-300 hover:bg-[#D4AF37] hover:text-[#1a0a0d] uppercase tracking-wider text-sm',

    // Typography
    headingClass: 'text-3xl font-serif font-bold text-[#FDF8F0]',
    subheadingClass: 'text-lg text-[#FDF8F0]/70',

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'Theater';
        const headerMenu = config.menus?.header || [];

        const menuItemsHtml = headerMenu.map(item =>
            `<a href="${item.url}" class="text-[#FDF8F0]/80 hover:text-[#D4AF37] transition-colors duration-300 tracking-wide">${item.title}</a>`
        ).join('');

        return `
            <!-- Theater Header -->
            <header class="theater-header fixed top-0 left-0 right-0 z-50 bg-[#1a0a0d]/95 backdrop-blur-md border-b border-[#D4AF37]/20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-20">
                        <!-- Logo -->
                        <a href="/" class="flex items-center group">
                            ${logo
                                ? `<img src="${logo}" alt="${siteName}" class="h-12 w-auto">`
                                : `<span class="text-2xl font-serif font-bold text-[#D4AF37] tracking-wider">${siteName}</span>`
                            }
                        </a>

                        <!-- Desktop Navigation -->
                        <nav class="hidden md:flex items-center space-x-8">
                            <a href="/events" class="text-[#FDF8F0]/80 hover:text-[#D4AF37] transition-colors duration-300 tracking-wide">Spectacole</a>
                            ${config.modules?.includes('shop') ? '<a href="/shop" class="text-[#FDF8F0]/80 hover:text-[#D4AF37] transition-colors duration-300 tracking-wide">Magazin</a>' : ''}
                            <a href="/blog" class="text-[#FDF8F0]/80 hover:text-[#D4AF37] transition-colors duration-300 tracking-wide">Noutăți</a>
                            ${menuItemsHtml}
                            <a href="/cart" class="relative text-[#D4AF37] hover:text-[#FDF8F0] transition-colors duration-300 p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="28">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span id="cart-badge" class="absolute -top-1 -right-1 bg-[#D4AF37] text-[#1a0a0d] text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>
                            <a href="/login" id="account-link" class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-[#D4AF37] to-[#B8962E] text-[#1a0a0d] font-semibold rounded transition-all duration-300 hover:shadow-lg hover:shadow-[#D4AF37]/30 uppercase tracking-wider text-sm">
                                Contul meu
                            </a>
                        </nav>

                        <!-- Mobile: Cart + Menu -->
                        <div class="md:hidden flex items-center gap-3">
                            <a href="/cart" class="relative p-2 text-[#D4AF37]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="28">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="absolute -top-1 -right-1 bg-[#D4AF37] text-[#1a0a0d] text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>
                            <button id="mobile-menu-btn" class="p-2 text-[#D4AF37] hover:text-[#FDF8F0] transition-colors" aria-label="Deschide meniu">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile Menu Overlay -->
            <div id="mobile-menu-overlay" class="theater-drawer-overlay hidden opacity-0"></div>

            <!-- Mobile Menu Drawer -->
            <div id="mobile-menu-drawer" class="theater-drawer translate-x-full">
                <div class="h-full flex flex-col">
                    <!-- Drawer Header -->
                    <div class="flex items-center justify-between p-5 border-b border-[#D4AF37]/20">
                        <span class="text-lg font-serif font-bold text-[#D4AF37] tracking-wider">Meniu</span>
                        <button id="mobile-menu-close" class="p-2 text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors" aria-label="Închide meniu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Drawer Navigation -->
                    <nav class="flex-1 overflow-y-auto p-4">
                        <div class="space-y-1">
                            <a href="/events" class="flex items-center gap-3 px-4 py-4 text-[#FDF8F0] hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-all duration-300 group">
                                <svg class="w-5 h-5 text-[#D4AF37]/70 group-hover:text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                                </svg>
                                <span class="tracking-wide">Spectacole</span>
                            </a>
                            ${config.modules?.includes('shop') ? `
                            <a href="/shop" class="flex items-center gap-3 px-4 py-4 text-[#FDF8F0] hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-all duration-300 group">
                                <svg class="w-5 h-5 text-[#D4AF37]/70 group-hover:text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span class="tracking-wide">Magazin</span>
                            </a>
                            ` : ''}
                            <a href="/blog" class="flex items-center gap-3 px-4 py-4 text-[#FDF8F0] hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-all duration-300 group">
                                <svg class="w-5 h-5 text-[#D4AF37]/70 group-hover:text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                                </svg>
                                <span class="tracking-wide">Noutăți</span>
                            </a>
                            ${headerMenu.map(item => `
                            <a href="${item.url}" class="flex items-center gap-3 px-4 py-4 text-[#FDF8F0] hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-all duration-300">
                                <svg class="w-5 h-5 text-[#D4AF37]/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                                <span class="tracking-wide">${item.title}</span>
                            </a>
                            `).join('')}
                        </div>

                        <!-- Divider with gold accent -->
                        <div class="my-4 flex items-center gap-3">
                            <div class="flex-1 h-px bg-gradient-to-r from-transparent via-[#D4AF37]/30 to-transparent"></div>
                        </div>

                        <div class="space-y-1">
                            <a href="/account" class="flex items-center gap-3 px-4 py-4 text-[#FDF8F0] hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-all duration-300 group">
                                <svg class="w-5 h-5 text-[#D4AF37]/70 group-hover:text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span class="tracking-wide">Contul meu</span>
                            </a>
                            <a href="/cart" class="flex items-center justify-between px-4 py-4 text-[#FDF8F0] hover:bg-[#D4AF37]/10 hover:text-[#D4AF37] rounded-lg transition-all duration-300 group">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-[#D4AF37]/70 group-hover:text-[#D4AF37]" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="35">
                                        <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span class="tracking-wide">Bilete rezervate</span>
                                </div>
                                <span class="bg-[#D4AF37] text-[#1a0a0d] text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center hidden">0</span>
                            </a>
                        </div>
                    </nav>

                    <!-- Drawer Footer -->
                    <div class="p-4 border-t border-[#D4AF37]/20">
                        <a href="/login" class="flex items-center justify-center gap-2 w-full px-6 py-3 bg-gradient-to-r from-[#D4AF37] to-[#B8962E] text-[#1a0a0d] font-semibold rounded transition-all duration-300 uppercase tracking-wider text-sm">
                            Conectare
                        </a>
                    </div>
                </div>
            </div>

            <!-- Toast Container -->
            <div id="toast-container" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2"></div>
        `;
    },

    renderFooter: (config: TixelloConfig): string => {
        const siteName = config.site?.title || 'Theater';
        const year = new Date().getFullYear();
        const social = config.social || {};
        const footerMenu = config.menus?.footer || [];

        const footerMenuHtml = footerMenu.map(item =>
            `<li><a href="${item.url}" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">${item.title}</a></li>`
        ).join('');

        const socialIcons = [];
        if (social.facebook) {
            socialIcons.push(`<a href="${social.facebook}" target="_blank" class="w-10 h-10 rounded-full border border-[#D4AF37]/30 flex items-center justify-center text-[#D4AF37]/70 hover:text-[#D4AF37] hover:border-[#D4AF37] hover:bg-[#D4AF37]/10 transition-all duration-300" aria-label="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>`);
        }
        if (social.instagram) {
            socialIcons.push(`<a href="${social.instagram}" target="_blank" class="w-10 h-10 rounded-full border border-[#D4AF37]/30 flex items-center justify-center text-[#D4AF37]/70 hover:text-[#D4AF37] hover:border-[#D4AF37] hover:bg-[#D4AF37]/10 transition-all duration-300" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>`);
        }
        if (social.twitter) {
            socialIcons.push(`<a href="${social.twitter}" target="_blank" class="w-10 h-10 rounded-full border border-[#D4AF37]/30 flex items-center justify-center text-[#D4AF37]/70 hover:text-[#D4AF37] hover:border-[#D4AF37] hover:bg-[#D4AF37]/10 transition-all duration-300" aria-label="Twitter"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>`);
        }
        if (social.youtube) {
            socialIcons.push(`<a href="${social.youtube}" target="_blank" class="w-10 h-10 rounded-full border border-[#D4AF37]/30 flex items-center justify-center text-[#D4AF37]/70 hover:text-[#D4AF37] hover:border-[#D4AF37] hover:bg-[#D4AF37]/10 transition-all duration-300" aria-label="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>`);
        }
        if (social.tiktok) {
            socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="w-10 h-10 rounded-full border border-[#D4AF37]/30 flex items-center justify-center text-[#D4AF37]/70 hover:text-[#D4AF37] hover:border-[#D4AF37] hover:bg-[#D4AF37]/10 transition-all duration-300" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>`);
        }
        if (social.linkedin) {
            socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="w-10 h-10 rounded-full border border-[#D4AF37]/30 flex items-center justify-center text-[#D4AF37]/70 hover:text-[#D4AF37] hover:border-[#D4AF37] hover:bg-[#D4AF37]/10 transition-all duration-300" aria-label="LinkedIn"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>`);
        }

        const socialHtml = socialIcons.length > 0
            ? `<div class="flex gap-3 mt-6">${socialIcons.join('')}</div>`
            : '';

        return `
            <footer class="theater-footer bg-[#0f0608] border-t border-[#D4AF37]/20 mt-16">
                <!-- Decorative curtain border -->
                <div class="h-1 bg-gradient-to-r from-transparent via-[#D4AF37] to-transparent opacity-50"></div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
                        <!-- Brand Column -->
                        <div class="col-span-2">
                            <h3 class="text-2xl font-serif font-bold text-[#D4AF37] mb-4 tracking-wider">${siteName}</h3>
                            <p class="text-[#FDF8F0]/60 mb-4 leading-relaxed">${config.site?.description || ''}</p>
                            ${config.site?.tagline ? `<p class="text-[#D4AF37]/80 font-serif italic">"${config.site.tagline}"</p>` : ''}
                            ${socialHtml}
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h4 class="text-sm font-semibold text-[#D4AF37] uppercase tracking-widest mb-5">Program</h4>
                            <ul class="space-y-3">
                                <li><a href="/events" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">Spectacole curente</a></li>
                                <li><a href="/past-events" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">Arhivă spectacole</a></li>
                                <li><a href="/blog" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">Noutăți</a></li>
                                <li><a href="/account" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">Contul meu</a></li>
                            </ul>
                        </div>

                        <!-- Legal -->
                        <div>
                            <h4 class="text-sm font-semibold text-[#D4AF37] uppercase tracking-widest mb-5">Informații</h4>
                            <ul class="space-y-3">
                                <li><a href="/terms" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">Termeni și condiții</a></li>
                                <li><a href="/privacy" class="text-[#FDF8F0]/60 hover:text-[#D4AF37] transition-colors duration-300">Confidențialitate</a></li>
                                ${footerMenuHtml}
                            </ul>
                        </div>
                    </div>

                    <!-- Bottom Bar -->
                    <div class="border-t border-[#D4AF37]/10 mt-12 pt-8">
                        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between text-sm">
                            <p class="text-[#FDF8F0]/50">&copy; ${year} ${siteName}. Toate drepturile rezervate.</p>
                            <div class="flex items-center gap-2 text-[#FDF8F0]/50">
                                <span>Powered by</span>
                                <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center">
                                    ${config.platform?.logo_dark
                                        ? `<img src="${config.platform.logo_dark}" alt="${config.platform?.name || 'Tixello'}" class="h-4 w-auto opacity-70 hover:opacity-100 transition-opacity">`
                                        : `<span class="text-[#D4AF37] font-semibold hover:underline">${config.platform?.name || 'Tixello'}</span>`
                                    }
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        `;
    },

    // Custom styles for theater template
    customStyles: `
        /* ============================================
           THEATER TEMPLATE - Custom Styles
           ============================================ */

        /* Google Font - Playfair Display for elegant serif headings */
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap');

        /* ============================================
           CSS Variables - Theater Theme
           ============================================ */
        :root {
            --theater-burgundy: #7A1527;
            --theater-burgundy-dark: #5a101d;
            --theater-gold: #D4AF37;
            --theater-gold-light: #E5C76B;
            --theater-cream: #FDF8F0;
            --theater-charcoal: #1a0a0d;
            --theater-charcoal-light: #2d1218;
            --theater-shadow: rgba(212, 175, 55, 0.15);
        }

        /* ============================================
           Typography
           ============================================ */
        .theater-header,
        .theater-footer,
        .theater-drawer {
            font-family: system-ui, -apple-system, sans-serif;
        }

        /* Serif headings throughout the page */
        h1, h2, h3, .font-serif {
            font-family: 'Playfair Display', Georgia, serif !important;
        }

        /* ============================================
           Body & Main Content
           ============================================ */
        body {
            background: linear-gradient(135deg, var(--theater-charcoal) 0%, #0f0608 100%);
            color: var(--theater-cream);
            min-height: 100vh;
        }

        main {
            padding-top: 5rem;
        }

        /* ============================================
           Mobile Drawer Styles
           ============================================ */
        .theater-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 6, 8, 0.85);
            backdrop-filter: blur(4px);
            z-index: 60;
            transition: opacity 0.3s ease;
        }

        .theater-drawer-overlay:not(.hidden) {
            opacity: 1;
        }

        .theater-drawer {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: 18rem;
            background: linear-gradient(180deg, var(--theater-charcoal) 0%, #0f0608 100%);
            border-left: 1px solid rgba(212, 175, 55, 0.2);
            z-index: 70;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
        }

        .theater-drawer:not(.translate-x-full) {
            transform: translateX(0);
        }

        /* ============================================
           Event Cards
           ============================================ */
        .event-card,
        [class*="event-card"] {
            background: var(--theater-charcoal) !important;
            border: 1px solid rgba(212, 175, 55, 0.15) !important;
            border-radius: 0.5rem;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .event-card:hover,
        [class*="event-card"]:hover {
            border-color: rgba(212, 175, 55, 0.4) !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4),
                        0 0 40px var(--theater-shadow);
            transform: translateY(-4px);
        }

        .event-card img {
            transition: transform 0.5s ease;
        }

        .event-card:hover img {
            transform: scale(1.05);
        }

        /* ============================================
           Form Inputs - Theater Style
           ============================================ */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="tel"],
        input[type="date"],
        textarea,
        select {
            background: rgba(253, 248, 240, 0.05) !important;
            border: 1px solid rgba(212, 175, 55, 0.2) !important;
            color: var(--theater-cream) !important;
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--theater-gold) !important;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
            background: rgba(253, 248, 240, 0.08) !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: rgba(253, 248, 240, 0.4);
        }

        /* ============================================
           Buttons
           ============================================ */
        .btn-primary,
        button[type="submit"],
        .primary-button {
            background: linear-gradient(135deg, var(--theater-gold) 0%, #B8962E 100%) !important;
            color: var(--theater-charcoal) !important;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover,
        button[type="submit"]:hover,
        .primary-button:hover {
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
            transform: translateY(-2px);
        }

        .btn-secondary,
        .secondary-button {
            background: transparent !important;
            border: 2px solid var(--theater-gold) !important;
            color: var(--theater-gold) !important;
        }

        .btn-secondary:hover,
        .secondary-button:hover {
            background: var(--theater-gold) !important;
            color: var(--theater-charcoal) !important;
        }

        /* ============================================
           Tables - Elegant styling
           ============================================ */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background: rgba(212, 175, 55, 0.1);
            color: var(--theater-gold);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(253, 248, 240, 0.08);
            color: var(--theater-cream);
        }

        tr:hover td {
            background: rgba(212, 175, 55, 0.05);
        }

        /* ============================================
           Cart & Checkout Pages
           ============================================ */
        .cart-item,
        [class*="cart-item"] {
            background: var(--theater-charcoal);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: border-color 0.3s ease;
        }

        .cart-item:hover {
            border-color: rgba(212, 175, 55, 0.3);
        }

        .cart-summary,
        [class*="summary"],
        [class*="order-total"] {
            background: linear-gradient(135deg, var(--theater-charcoal-light) 0%, var(--theater-charcoal) 100%);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 0.5rem;
        }

        /* ============================================
           Quantity Selector
           ============================================ */
        .quantity-selector,
        [class*="quantity"] {
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 0.375rem;
            overflow: hidden;
        }

        .quantity-selector button,
        [class*="quantity"] button {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(212, 175, 55, 0.1);
            color: var(--theater-gold);
            border: none;
            transition: all 0.2s ease;
        }

        .quantity-selector button:hover,
        [class*="quantity"] button:hover {
            background: rgba(212, 175, 55, 0.2);
        }

        .quantity-selector input,
        [class*="quantity"] input {
            width: 3rem;
            text-align: center;
            background: transparent !important;
            border: none !important;
            color: var(--theater-cream);
            font-weight: 600;
        }

        /* ============================================
           Toast Notifications
           ============================================ */
        .toast {
            background: linear-gradient(135deg, var(--theater-charcoal-light) 0%, var(--theater-charcoal) 100%);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
            color: var(--theater-cream);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            animation: toast-slide-in 0.3s ease;
        }

        .toast-success {
            border-left: 3px solid #10B981;
        }

        .toast-error {
            border-left: 3px solid #EF4444;
        }

        .toast-warning {
            border-left: 3px solid var(--theater-gold);
        }

        @keyframes toast-slide-in {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* ============================================
           Modal Dialogs
           ============================================ */
        .modal-overlay,
        [class*="modal-backdrop"] {
            background: rgba(15, 6, 8, 0.9);
            backdrop-filter: blur(8px);
        }

        .modal,
        .modal-content,
        [class*="modal-content"] {
            background: linear-gradient(135deg, var(--theater-charcoal-light) 0%, var(--theater-charcoal) 100%);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5),
                        0 0 80px rgba(212, 175, 55, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid rgba(212, 175, 55, 0.15);
            padding: 1.5rem;
        }

        .modal-header h2,
        .modal-header h3 {
            color: var(--theater-gold);
        }

        /* ============================================
           Skeleton Loading
           ============================================ */
        .skeleton,
        [class*="skeleton"] {
            background: linear-gradient(
                90deg,
                rgba(253, 248, 240, 0.05) 0%,
                rgba(212, 175, 55, 0.1) 50%,
                rgba(253, 248, 240, 0.05) 100%
            );
            background-size: 200% 100%;
            animation: skeleton-shimmer 1.5s infinite;
            border-radius: 0.375rem;
        }

        @keyframes skeleton-shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* ============================================
           Page Transitions
           ============================================ */
        .page-enter {
            opacity: 0;
            transform: translateY(10px);
        }

        .page-enter-active {
            opacity: 1;
            transform: translateY(0);
            transition: all 0.3s ease;
        }

        /* ============================================
           Scrollbar Styling
           ============================================ */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--theater-charcoal);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(212, 175, 55, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(212, 175, 55, 0.5);
        }

        /* ============================================
           Links
           ============================================ */
        a:not([class]) {
            color: var(--theater-gold);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:not([class]):hover {
            color: var(--theater-gold-light);
            text-decoration: underline;
        }

        /* ============================================
           Selection
           ============================================ */
        ::selection {
            background: rgba(212, 175, 55, 0.3);
            color: var(--theater-cream);
        }

        /* ============================================
           Print Styles
           ============================================ */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }

            .theater-header,
            .theater-footer,
            .theater-drawer,
            .theater-drawer-overlay,
            #toast-container {
                display: none !important;
            }

            main {
                padding-top: 0 !important;
            }

            a {
                color: black !important;
                text-decoration: underline;
            }

            .event-card,
            .cart-item {
                border: 1px solid #ccc !important;
                box-shadow: none !important;
            }
        }

        /* ============================================
           Accessibility
           ============================================ */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus visible styles */
        *:focus-visible {
            outline: 2px solid var(--theater-gold);
            outline-offset: 2px;
        }

        /* ============================================
           Event Detail Page
           ============================================ */
        .event-hero {
            position: relative;
            overflow: hidden;
        }

        .event-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 0%, var(--theater-charcoal) 100%);
            z-index: 1;
        }

        .event-info {
            background: var(--theater-charcoal);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 0.5rem;
        }

        /* ============================================
           Account Pages
           ============================================ */
        .account-card,
        [class*="dashboard-card"] {
            background: var(--theater-charcoal);
            border: 1px solid rgba(212, 175, 55, 0.15);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .account-nav a,
        .dashboard-nav a {
            color: var(--theater-cream);
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .account-nav a:hover,
        .dashboard-nav a:hover,
        .account-nav a.active,
        .dashboard-nav a.active {
            background: rgba(212, 175, 55, 0.1);
            color: var(--theater-gold);
        }

        /* ============================================
           Badge Styles
           ============================================ */
        .badge,
        [class*="badge"],
        [class*="tag"] {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 9999px;
            background: rgba(212, 175, 55, 0.15);
            color: var(--theater-gold);
            border: 1px solid rgba(212, 175, 55, 0.3);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .badge-warning {
            background: rgba(212, 175, 55, 0.15);
            color: var(--theater-gold);
            border-color: rgba(212, 175, 55, 0.3);
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.15);
            color: #EF4444;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* ============================================
           Responsive Adjustments
           ============================================ */
        @media (max-width: 768px) {
            main {
                padding-top: 5rem;
                padding-bottom: 1rem;
            }

            h1 {
                font-size: 1.75rem !important;
            }

            h2 {
                font-size: 1.5rem !important;
            }

            .theater-footer {
                padding-bottom: 2rem;
            }
        }
    `
};

// Register the template
TemplateManager.registerTemplate('theater', theaterTemplate);

export default theaterTemplate;
