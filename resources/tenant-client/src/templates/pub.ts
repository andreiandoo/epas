import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

/**
 * Pub Template
 *
 * A warm, inviting template designed for pubs, bars, breweries,
 * and casual event venues. Features amber/copper tones, dark wood
 * aesthetics, and a cozy atmosphere.
 *
 * Design principles:
 * - Hybrid Tailwind/CSS approach
 * - Warm amber and dark wood color palette
 * - Casual, friendly typography
 * - Cozy, inviting atmosphere
 */

const pubTemplate: TemplateConfig = {
    name: 'pub',

    // Layout
    headerClass: 'fixed top-0 left-0 right-0 z-50 bg-[#1C1208]/95 backdrop-blur-md border-b border-[#D97706]/20',
    footerClass: 'bg-[#0F0A04] border-t border-[#D97706]/20 mt-16',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',

    // Hero
    heroClass: 'text-center py-20 bg-gradient-to-b from-[#1C1208] via-[#2D1B0E] to-[#1C1208] text-[#FEF3E2]',
    heroTitleClass: 'text-5xl md:text-6xl font-bold text-[#FEF3E2] mb-6',
    heroSubtitleClass: 'text-xl text-[#FEF3E2]/80 mb-10 max-w-2xl mx-auto',

    // Cards
    cardClass: 'bg-[#1C1208] border border-[#D97706]/20 rounded-xl overflow-hidden shadow-lg transition-all duration-300',
    cardHoverClass: 'hover:border-[#D97706]/40 hover:shadow-2xl hover:shadow-[#D97706]/10',

    // Buttons
    primaryButtonClass: 'inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-[#D97706] to-[#B45309] text-white font-semibold rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-[#D97706]/30 hover:-translate-y-0.5',
    secondaryButtonClass: 'inline-flex items-center gap-2 px-6 py-3 border-2 border-[#D97706] text-[#D97706] font-semibold rounded-lg transition-all duration-300 hover:bg-[#D97706] hover:text-white',

    // Typography
    headingClass: 'text-3xl font-bold text-[#FEF3E2]',
    subheadingClass: 'text-lg text-[#FEF3E2]/70',

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'The Pub';
        const headerMenu = config.menus?.header || [];

        const menuItemsHtml = headerMenu.map(item =>
            `<a href="${item.url}" class="text-[#FEF3E2]/80 hover:text-[#D97706] transition-colors duration-300">${item.title}</a>`
        ).join('');

        return `
            <!-- Pub Header -->
            <header class="pub-header fixed top-0 left-0 right-0 z-50 bg-[#1C1208]/95 backdrop-blur-md border-b border-[#D97706]/20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-18">
                        <!-- Logo -->
                        <a href="/" class="flex items-center gap-3 py-4">
                            ${logo
                                ? `<img src="${logo}" alt="${siteName}" class="h-10 w-auto">`
                                : `<span class="text-2xl font-bold text-[#D97706]">${siteName}</span>`
                            }
                        </a>

                        <!-- Desktop Navigation -->
                        <nav class="hidden md:flex items-center space-x-8">
                            <a href="/events" class="text-[#FEF3E2]/80 hover:text-[#D97706] transition-colors duration-300 font-medium">Evenimente</a>
                            ${config.modules?.includes('shop') ? '<a href="/shop" class="text-[#FEF3E2]/80 hover:text-[#D97706] transition-colors duration-300 font-medium">Magazin</a>' : ''}
                            <a href="/blog" class="text-[#FEF3E2]/80 hover:text-[#D97706] transition-colors duration-300 font-medium">Blog</a>
                            ${menuItemsHtml}
                            <a href="/cart" class="relative text-[#D97706] hover:text-[#FEF3E2] transition-colors duration-300 p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="28">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span id="cart-badge" class="absolute -top-1 -right-1 bg-[#D97706] text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>
                            <a href="/login" id="account-link" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-[#D97706] to-[#B45309] text-white font-semibold rounded-lg transition-all duration-300 hover:shadow-lg hover:shadow-[#D97706]/30">
                                Contul meu
                            </a>
                        </nav>

                        <!-- Mobile: Cart + Menu -->
                        <div class="md:hidden flex items-center gap-2">
                            <a href="/cart" class="relative p-2 text-[#D97706]">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="28">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="absolute -top-1 -right-1 bg-[#D97706] text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>
                            <button id="mobile-menu-btn" class="p-2 text-[#D97706] hover:text-[#FEF3E2] transition-colors" aria-label="Deschide meniu">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile Menu Overlay -->
            <div id="mobile-menu-overlay" class="pub-drawer-overlay hidden opacity-0"></div>

            <!-- Mobile Menu Drawer -->
            <div id="mobile-menu-drawer" class="pub-drawer translate-x-full">
                <div class="h-full flex flex-col">
                    <!-- Drawer Header -->
                    <div class="flex items-center justify-between p-5 border-b border-[#D97706]/20">
                        <span class="text-lg font-bold text-[#D97706]">Meniu</span>
                        <button id="mobile-menu-close" class="p-2 text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors" aria-label="Închide meniu">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Drawer Navigation -->
                    <nav class="flex-1 overflow-y-auto p-4">
                        <div class="space-y-1">
                            <a href="/events" class="flex items-center gap-3 px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200 group">
                                <svg class="w-5 h-5 text-[#D97706]/60 group-hover:text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span>Evenimente</span>
                            </a>
                            ${config.modules?.includes('shop') ? `
                            <a href="/shop" class="flex items-center gap-3 px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200 group">
                                <svg class="w-5 h-5 text-[#D97706]/60 group-hover:text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span>Magazin</span>
                            </a>
                            ` : ''}
                            <a href="/blog" class="flex items-center gap-3 px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200 group">
                                <svg class="w-5 h-5 text-[#D97706]/60 group-hover:text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                                </svg>
                                <span>Blog</span>
                            </a>
                            ${headerMenu.map(item => `
                            <a href="${item.url}" class="flex items-center gap-3 px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200">
                                <svg class="w-5 h-5 text-[#D97706]/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                                <span>${item.title}</span>
                            </a>
                            `).join('')}
                        </div>

                        <!-- Divider -->
                        <div class="my-4 h-px bg-gradient-to-r from-transparent via-[#D97706]/30 to-transparent"></div>

                        <div class="space-y-1">
                            <a href="/account" class="flex items-center gap-3 px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200 group">
                                <svg class="w-5 h-5 text-[#D97706]/60 group-hover:text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span>Contul meu</span>
                            </a>
                            ${config.modules?.includes('gamification') ? `
                            <a href="/account/points" class="flex items-center gap-3 px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200 group">
                                <svg class="w-5 h-5 text-[#D97706] group-hover:text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Punctele mele</span>
                            </a>
                            ` : ''}
                            <a href="/cart" class="flex items-center justify-between px-4 py-3.5 text-[#FEF3E2] hover:bg-[#D97706]/10 hover:text-[#D97706] rounded-lg transition-all duration-200 group">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-[#D97706]/60 group-hover:text-[#D97706]" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="35">
                                        <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>Coș</span>
                                </div>
                                <span class="bg-[#D97706] text-white text-xs font-bold rounded-full h-5 min-w-[1.25rem] px-1 flex items-center justify-center hidden">0</span>
                            </a>
                        </div>
                    </nav>

                    <!-- Drawer Footer -->
                    <div class="p-4 border-t border-[#D97706]/20">
                        <a href="/login" class="flex items-center justify-center gap-2 w-full px-6 py-3 bg-gradient-to-r from-[#D97706] to-[#B45309] text-white font-semibold rounded-lg transition-all duration-300">
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
        const siteName = config.site?.title || 'The Pub';
        const year = new Date().getFullYear();
        const social = config.social || {};
        const footerMenu = config.menus?.footer || [];

        const footerMenuHtml = footerMenu.map(item =>
            `<li><a href="${item.url}" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">${item.title}</a></li>`
        ).join('');

        const socialIcons = [];
        if (social.facebook) {
            socialIcons.push(`<a href="${social.facebook}" target="_blank" class="w-10 h-10 rounded-lg bg-[#D97706]/10 flex items-center justify-center text-[#D97706]/70 hover:text-[#D97706] hover:bg-[#D97706]/20 transition-all duration-300" aria-label="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>`);
        }
        if (social.instagram) {
            socialIcons.push(`<a href="${social.instagram}" target="_blank" class="w-10 h-10 rounded-lg bg-[#D97706]/10 flex items-center justify-center text-[#D97706]/70 hover:text-[#D97706] hover:bg-[#D97706]/20 transition-all duration-300" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>`);
        }
        if (social.twitter) {
            socialIcons.push(`<a href="${social.twitter}" target="_blank" class="w-10 h-10 rounded-lg bg-[#D97706]/10 flex items-center justify-center text-[#D97706]/70 hover:text-[#D97706] hover:bg-[#D97706]/20 transition-all duration-300" aria-label="Twitter"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>`);
        }
        if (social.youtube) {
            socialIcons.push(`<a href="${social.youtube}" target="_blank" class="w-10 h-10 rounded-lg bg-[#D97706]/10 flex items-center justify-center text-[#D97706]/70 hover:text-[#D97706] hover:bg-[#D97706]/20 transition-all duration-300" aria-label="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>`);
        }
        if (social.tiktok) {
            socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="w-10 h-10 rounded-lg bg-[#D97706]/10 flex items-center justify-center text-[#D97706]/70 hover:text-[#D97706] hover:bg-[#D97706]/20 transition-all duration-300" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>`);
        }
        if (social.linkedin) {
            socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="w-10 h-10 rounded-lg bg-[#D97706]/10 flex items-center justify-center text-[#D97706]/70 hover:text-[#D97706] hover:bg-[#D97706]/20 transition-all duration-300" aria-label="LinkedIn"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>`);
        }

        const socialHtml = socialIcons.length > 0
            ? `<div class="flex gap-2 mt-6">${socialIcons.join('')}</div>`
            : '';

        return `
            <footer class="pub-footer bg-[#0F0A04] border-t border-[#D97706]/20 mt-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
                        <!-- Brand Column -->
                        <div class="col-span-2">
                            <h3 class="text-2xl font-bold text-[#D97706] mb-4">${siteName}</h3>
                            <p class="text-[#FEF3E2]/60 mb-4 leading-relaxed">${config.site?.description || ''}</p>
                            ${config.site?.tagline ? `<p class="text-[#D97706]/80 font-medium">${config.site.tagline}</p>` : ''}
                            ${socialHtml}
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h4 class="text-sm font-semibold text-[#D97706] uppercase tracking-wide mb-5">Explorează</h4>
                            <ul class="space-y-3">
                                <li><a href="/events" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">Evenimente</a></li>
                                <li><a href="/past-events" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">Evenimente trecute</a></li>
                                <li><a href="/blog" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">Blog</a></li>
                                <li><a href="/account" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">Contul meu</a></li>
                            </ul>
                        </div>

                        <!-- Legal -->
                        <div>
                            <h4 class="text-sm font-semibold text-[#D97706] uppercase tracking-wide mb-5">Legal</h4>
                            <ul class="space-y-3">
                                <li><a href="/terms" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">Termeni și condiții</a></li>
                                <li><a href="/privacy" class="text-[#FEF3E2]/60 hover:text-[#D97706] transition-colors duration-300">Confidențialitate</a></li>
                                ${footerMenuHtml}
                            </ul>
                        </div>
                    </div>

                    <!-- Bottom Bar -->
                    <div class="border-t border-[#D97706]/10 mt-12 pt-8">
                        <div class="flex flex-col sm:flex-row gap-4 items-center justify-between text-sm">
                            <p class="text-[#FEF3E2]/50">&copy; ${year} ${siteName}. Toate drepturile rezervate.</p>
                            <div class="flex items-center gap-2 text-[#FEF3E2]/50">
                                <span>Powered by</span>
                                <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center">
                                    ${config.platform?.logo_dark
                                        ? `<img src="${config.platform.logo_dark}" alt="${config.platform?.name || 'Tixello'}" class="h-4 w-auto opacity-70 hover:opacity-100 transition-opacity">`
                                        : `<span class="text-[#D97706] font-semibold hover:underline">${config.platform?.name || 'Tixello'}</span>`
                                    }
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        `;
    },

    // Custom styles for pub template
    customStyles: `
        /* ============================================
           PUB TEMPLATE - Custom Styles
           ============================================ */

        /* ============================================
           CSS Variables - Pub Theme
           ============================================ */
        :root {
            --pub-amber: #D97706;
            --pub-amber-dark: #B45309;
            --pub-amber-light: #F59E0B;
            --pub-wood: #1C1208;
            --pub-wood-dark: #0F0A04;
            --pub-wood-light: #2D1B0E;
            --pub-cream: #FEF3E2;
            --pub-shadow: rgba(217, 119, 6, 0.15);
        }

        /* ============================================
           Body & Main Content
           ============================================ */
        body {
            background: linear-gradient(180deg, var(--pub-wood) 0%, var(--pub-wood-dark) 100%);
            color: var(--pub-cream);
            min-height: 100vh;
        }

        main {
            padding-top: 4.5rem;
        }

        /* ============================================
           Mobile Drawer Styles
           ============================================ */
        .pub-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 10, 4, 0.85);
            backdrop-filter: blur(4px);
            z-index: 60;
            transition: opacity 0.3s ease;
        }

        .pub-drawer-overlay:not(.hidden) {
            opacity: 1;
        }

        .pub-drawer {
            position: fixed;
            top: 0;
            right: 0;
            height: 100%;
            width: 18rem;
            background: linear-gradient(180deg, var(--pub-wood) 0%, var(--pub-wood-dark) 100%);
            border-left: 1px solid rgba(217, 119, 6, 0.2);
            z-index: 70;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
        }

        .pub-drawer:not(.translate-x-full) {
            transform: translateX(0);
        }

        /* ============================================
           Event Cards
           ============================================ */
        .event-card,
        [class*="event-card"] {
            background: var(--pub-wood) !important;
            border: 1px solid rgba(217, 119, 6, 0.15) !important;
            border-radius: 0.75rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .event-card:hover,
        [class*="event-card"]:hover {
            border-color: rgba(217, 119, 6, 0.4) !important;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3),
                        0 0 30px var(--pub-shadow);
            transform: translateY(-4px);
        }

        .event-card img {
            transition: transform 0.4s ease;
        }

        .event-card:hover img {
            transform: scale(1.05);
        }

        /* ============================================
           Form Inputs - Pub Style
           ============================================ */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="tel"],
        input[type="date"],
        textarea,
        select {
            background: rgba(254, 243, 226, 0.05) !important;
            border: 1px solid rgba(217, 119, 6, 0.25) !important;
            color: var(--pub-cream) !important;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--pub-amber) !important;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
            background: rgba(254, 243, 226, 0.08) !important;
        }

        input::placeholder,
        textarea::placeholder {
            color: rgba(254, 243, 226, 0.4);
        }

        /* ============================================
           Buttons
           ============================================ */
        .btn-primary,
        button[type="submit"],
        .primary-button {
            background: linear-gradient(135deg, var(--pub-amber) 0%, var(--pub-amber-dark) 100%) !important;
            color: white !important;
            font-weight: 600;
            border: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover,
        button[type="submit"]:hover,
        .primary-button:hover {
            box-shadow: 0 8px 25px rgba(217, 119, 6, 0.35);
            transform: translateY(-2px);
        }

        .btn-secondary,
        .secondary-button {
            background: transparent !important;
            border: 2px solid var(--pub-amber) !important;
            color: var(--pub-amber) !important;
            border-radius: 0.5rem;
        }

        .btn-secondary:hover,
        .secondary-button:hover {
            background: var(--pub-amber) !important;
            color: white !important;
        }

        /* ============================================
           Tables
           ============================================ */
        table {
            border-collapse: collapse;
            width: 100%;
        }

        th {
            background: rgba(217, 119, 6, 0.1);
            color: var(--pub-amber);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(217, 119, 6, 0.2);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid rgba(254, 243, 226, 0.08);
            color: var(--pub-cream);
        }

        tr:hover td {
            background: rgba(217, 119, 6, 0.05);
        }

        /* ============================================
           Cart & Checkout
           ============================================ */
        .cart-item,
        [class*="cart-item"] {
            background: var(--pub-wood);
            border: 1px solid rgba(217, 119, 6, 0.15);
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: border-color 0.3s ease;
        }

        .cart-item:hover {
            border-color: rgba(217, 119, 6, 0.3);
        }

        .cart-summary,
        [class*="summary"],
        [class*="order-total"] {
            background: linear-gradient(135deg, var(--pub-wood-light) 0%, var(--pub-wood) 100%);
            border: 1px solid rgba(217, 119, 6, 0.2);
            border-radius: 0.75rem;
        }

        /* ============================================
           Quantity Selector
           ============================================ */
        .quantity-selector,
        [class*="quantity"] {
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(217, 119, 6, 0.3);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .quantity-selector button,
        [class*="quantity"] button {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(217, 119, 6, 0.1);
            color: var(--pub-amber);
            border: none;
            transition: all 0.2s ease;
        }

        .quantity-selector button:hover,
        [class*="quantity"] button:hover {
            background: rgba(217, 119, 6, 0.2);
        }

        .quantity-selector input,
        [class*="quantity"] input {
            width: 3rem;
            text-align: center;
            background: transparent !important;
            border: none !important;
            color: var(--pub-cream);
            font-weight: 600;
        }

        /* ============================================
           Toast Notifications
           ============================================ */
        .toast {
            background: linear-gradient(135deg, var(--pub-wood-light) 0%, var(--pub-wood) 100%);
            border: 1px solid rgba(217, 119, 6, 0.3);
            border-radius: 0.5rem;
            padding: 1rem 1.5rem;
            color: var(--pub-cream);
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
            border-left: 3px solid var(--pub-amber);
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
            background: rgba(15, 10, 4, 0.9);
            backdrop-filter: blur(8px);
        }

        .modal,
        .modal-content,
        [class*="modal-content"] {
            background: linear-gradient(135deg, var(--pub-wood-light) 0%, var(--pub-wood) 100%);
            border: 1px solid rgba(217, 119, 6, 0.2);
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            border-bottom: 1px solid rgba(217, 119, 6, 0.15);
            padding: 1.5rem;
        }

        .modal-header h2,
        .modal-header h3 {
            color: var(--pub-amber);
        }

        /* ============================================
           Skeleton Loading
           ============================================ */
        .skeleton,
        [class*="skeleton"] {
            background: linear-gradient(
                90deg,
                rgba(254, 243, 226, 0.05) 0%,
                rgba(217, 119, 6, 0.1) 50%,
                rgba(254, 243, 226, 0.05) 100%
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
           Scrollbar Styling
           ============================================ */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--pub-wood);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(217, 119, 6, 0.3);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(217, 119, 6, 0.5);
        }

        /* ============================================
           Links
           ============================================ */
        a:not([class]) {
            color: var(--pub-amber);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:not([class]):hover {
            color: var(--pub-amber-light);
            text-decoration: underline;
        }

        /* ============================================
           Selection
           ============================================ */
        ::selection {
            background: rgba(217, 119, 6, 0.3);
            color: var(--pub-cream);
        }

        /* ============================================
           Print Styles
           ============================================ */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }

            .pub-header,
            .pub-footer,
            .pub-drawer,
            .pub-drawer-overlay,
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

        *:focus-visible {
            outline: 2px solid var(--pub-amber);
            outline-offset: 2px;
        }

        /* ============================================
           Account Pages
           ============================================ */
        .account-card,
        [class*="dashboard-card"] {
            background: var(--pub-wood);
            border: 1px solid rgba(217, 119, 6, 0.15);
            border-radius: 0.75rem;
            padding: 1.5rem;
        }

        .account-nav a,
        .dashboard-nav a {
            color: var(--pub-cream);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }

        .account-nav a:hover,
        .dashboard-nav a:hover,
        .account-nav a.active,
        .dashboard-nav a.active {
            background: rgba(217, 119, 6, 0.1);
            color: var(--pub-amber);
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
            border-radius: 0.375rem;
            background: rgba(217, 119, 6, 0.15);
            color: var(--pub-amber);
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
            border-color: rgba(16, 185, 129, 0.3);
        }

        .badge-warning {
            background: rgba(217, 119, 6, 0.15);
            color: var(--pub-amber);
            border-color: rgba(217, 119, 6, 0.3);
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
                padding-top: 4.5rem;
                padding-bottom: 1rem;
            }

            h1 {
                font-size: 1.75rem !important;
            }

            h2 {
                font-size: 1.5rem !important;
            }

            .pub-footer {
                padding-bottom: 2rem;
            }
        }
    `
};

// Register the template
TemplateManager.registerTemplate('pub', pubTemplate);

export default pubTemplate;
