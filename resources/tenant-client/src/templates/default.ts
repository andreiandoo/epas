import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

const defaultTemplate: TemplateConfig = {
    name: 'default',

    // Layout
    headerClass: 'bg-white shadow-sm border-b',
    footerClass: 'bg-gray-50 border-t',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',

    // Hero
    heroClass: 'text-center py-16 bg-gradient-to-br from-gray-50 to-gray-100',
    heroTitleClass: 'text-4xl md:text-5xl font-bold text-gray-900 mb-4',
    heroSubtitleClass: 'text-xl text-gray-600 mb-8 max-w-2xl mx-auto',

    // Cards
    cardClass: 'bg-white rounded-lg shadow-md overflow-hidden',
    cardHoverClass: 'hover:shadow-lg transition',

    // Buttons - using CSS variables
    primaryButtonClass: 'inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-primary hover:bg-primary-dark transition',
    secondaryButtonClass: 'inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition',

    // Typography
    headingClass: 'text-2xl font-bold text-gray-900',
    subheadingClass: 'text-lg text-gray-600',

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'Tixello';
        const headerMenu = config.menus?.header || [];

        // Generate header menu items
        const menuItemsHtml = headerMenu.map(item =>
            `<a href="${item.url}" class="text-gray-600 hover:text-primary transition">${item.title}</a>`
        ).join('');

        return `
            <header class="bg-white shadow-sm border-b sticky top-0 z-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-16">
                        <a href="/" class="flex items-center">
                            ${logo
                                ? `<img src="${logo}" alt="${siteName}" class="h-8 w-auto">`
                                : `<span class="text-xl font-bold text-primary">${siteName}</span>`
                            }
                        </a>
                        <nav class="hidden md:flex items-center space-x-6">
                            <a href="/events" class="text-gray-600 hover:text-primary transition">Evenimente</a>
                            <a href="/blog" class="text-gray-600 hover:text-primary transition">Blog</a>
                            ${menuItemsHtml}
                            <a href="/cart" class="relative text-primary hover:text-gray-900 transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="30">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"/>
                                </svg>
                                <span id="cart-badge" class="absolute -top-2 -right-2 bg-primary text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>
                            <a href="/login" id="account-link" class="btn-primary px-4 py-2 rounded-lg text-sm">Contul meu</a>
                        </nav>
                        <!-- Mobile: Cart + Menu buttons -->
                        <div class="md:hidden flex items-center gap-2">
                            <a href="/cart" class="relative p-2 text-primary">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 512 512" stroke-width="30">
                                    <path d="M336.333 416.667v-32.134M336.333 320.267v-32.134M336.333 223.867v-32.134M336.333 127.467V95.333M497 191.733c-35.467 0-64.267 28.799-64.267 64.267s28.8 64.267 64.267 64.267v37.435c0 38.214-20.75 58.965-58.965 58.965H73.965C35.75 416.667 15 395.917 15 357.702v-37.435c35.467 0 64.267-28.799 64.267-64.267S50.467 191.733 15 191.733v-37.435c0-38.214 20.75-58.965 58.965-58.965h364.07c38.215 0 58.965 20.75 58.965 58.965v37.435z" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10"/>
                                </svg>
                                <span id="cart-badge-mobile" class="absolute -top-1 -right-1 bg-primary text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                            </a>
                            <button class="p-2" id="mobile-menu-btn" aria-label="Open menu">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile Menu Drawer -->
            <div id="mobile-menu-overlay" class="fixed inset-0 bg-black/50 z-[60] hidden opacity-0 transition-opacity duration-300"></div>
            <div id="mobile-menu-drawer" class="fixed top-0 right-0 h-full w-72 bg-white shadow-xl z-[70] transform translate-x-full transition-transform duration-300 ease-out">
                <div class="flex items-center justify-between p-4 border-b">
                    <span class="font-semibold text-gray-900">Meniu</span>
                    <button id="mobile-menu-close" class="p-2 hover:bg-gray-100 rounded-lg" aria-label="Close menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <nav class="p-4 flex flex-col space-y-1">
                    <a href="/events" class="px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">Evenimente</a>
                    <a href="/blog" class="px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">Blog</a>
                    ${headerMenu.map(item =>
                        `<a href="${item.url}" class="px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">${item.title}</a>`
                    ).join('')}
                    <div class="border-t my-2"></div>
                    <a href="/account" class="px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition">Contul meu</a>
                    <a href="/cart" class="px-4 py-3 text-gray-700 hover:bg-gray-100 rounded-lg transition flex items-center justify-between">
                        <span>Coș</span>
                        <span id="cart-badge-menu" class="bg-primary text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                    </a>
                </nav>
            </div>
        `;
    },

    renderFooter: (config: TixelloConfig): string => {
        const siteName = config.site?.title || 'Tixello';
        const year = new Date().getFullYear();
        const social = config.social || {};
        const footerMenu = config.menus?.footer || [];

        // Generate footer menu items
        const footerMenuHtml = footerMenu.map(item =>
            `<li><a href="${item.url}" class="text-gray-600 hover:text-primary">${item.title}</a></li>`
        ).join('');

        // Generate social icons HTML
        const socialIcons = [];
        if (social.facebook) {
            socialIcons.push(`<a href="${social.facebook}" target="_blank" class="text-gray-400 hover:text-primary" aria-label="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>`);
        }
        if (social.instagram) {
            socialIcons.push(`<a href="${social.instagram}" target="_blank" class="text-gray-400 hover:text-primary" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>`);
        }
        if (social.twitter) {
            socialIcons.push(`<a href="${social.twitter}" target="_blank" class="text-gray-400 hover:text-primary" aria-label="Twitter"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>`);
        }
        if (social.youtube) {
            socialIcons.push(`<a href="${social.youtube}" target="_blank" class="text-gray-400 hover:text-primary" aria-label="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>`);
        }
        if (social.tiktok) {
            socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="text-gray-400 hover:text-primary" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>`);
        }
        if (social.linkedin) {
            socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="text-gray-400 hover:text-primary" aria-label="LinkedIn"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>`);
        }

        const socialHtml = socialIcons.length > 0
            ? `<div class="flex space-x-4 mt-4">${socialIcons.join('')}</div>`
            : '';

        return `
            <footer class="bg-gray-50 border-t mt-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
                        <div class="col-span-2">
                            <h3 class="text-base md:text-lg font-bold text-gray-900 mb-3 md:mb-4">${siteName}</h3>
                            <p class="text-gray-600 text-sm mb-3 md:mb-4 line-clamp-3">${config.site?.description || ''}</p>
                            ${socialHtml}
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 md:mb-4 text-sm md:text-base">Linkuri rapide</h4>
                            <ul class="space-y-2 text-sm">
                                <li><a href="/events" class="text-gray-600 hover:text-primary">Evenimente</a></li>
                                <li><a href="/past-events" class="text-gray-600 hover:text-primary">Evenimente trecute</a></li>
                                <li><a href="/blog" class="text-gray-600 hover:text-primary">Blog</a></li>
                                <li><a href="/account" class="text-gray-600 hover:text-primary">Contul meu</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3 md:mb-4 text-sm md:text-base">Legal</h4>
                            <ul class="space-y-2 text-sm">
                                <li><a href="/terms" class="text-gray-600 hover:text-primary">Termeni și condiții</a></li>
                                <li><a href="/privacy" class="text-gray-600 hover:text-primary">Confidențialitate</a></li>
                                ${footerMenuHtml}
                            </ul>
                        </div>
                    </div>
                    <div class="border-t mt-6 md:mt-8 pt-6 md:pt-8 flex flex-col sm:flex-row gap-3 sm:gap-4 items-center justify-between text-xs sm:text-sm text-gray-600">
                        <p class="text-center sm:text-left">&copy; ${year} ${siteName}</p>
                        <div class="flex items-center gap-2 text-gray-600">
                            <span>Powered by</span>
                            <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center">
                                ${config.platform?.logo_light
                                    ? `<img src="${config.platform.logo_light}" alt="${config.platform?.name || 'Tixello'}" class="h-3 w-auto">`
                                    : `<span class="text-primary font-semibold hover:underline">${config.platform?.name || 'Tixello'}</span>`
                                }
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        `;
    }
};

// Register the template
TemplateManager.registerTemplate('default', defaultTemplate);

export default defaultTemplate;
