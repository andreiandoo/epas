import { TixelloConfig } from '../core/ConfigManager';
import { TemplateConfig, TemplateManager } from './TemplateManager';

const modernTemplate: TemplateConfig = {
    name: 'modern',

    // Layout - darker, more modern
    headerClass: 'bg-gray-900 text-white',
    footerClass: 'bg-gray-900 text-white',
    containerClass: 'max-w-7xl mx-auto px-4 sm:px-6 lg:px-8',

    // Hero - gradient with primary color
    heroClass: 'text-center py-20 bg-gradient-to-br from-primary to-secondary text-white',
    heroTitleClass: 'text-5xl md:text-6xl font-extrabold mb-6',
    heroSubtitleClass: 'text-xl opacity-90 mb-10 max-w-2xl mx-auto',

    // Cards - with rounded corners and subtle shadows
    cardClass: 'bg-white rounded-xl shadow-lg overflow-hidden',
    cardHoverClass: 'hover:shadow-2xl hover:-translate-y-1 transition-all duration-300',

    // Buttons
    primaryButtonClass: 'inline-flex items-center px-8 py-4 border border-transparent text-base font-bold rounded-full text-white bg-primary hover:bg-primary-dark transition shadow-lg hover:shadow-xl',
    secondaryButtonClass: 'inline-flex items-center px-8 py-4 border-2 border-white text-base font-bold rounded-full text-white hover:bg-white hover:text-gray-900 transition',

    // Typography
    headingClass: 'text-3xl font-extrabold text-gray-900',
    subheadingClass: 'text-lg text-gray-500',

    renderHeader: (config: TixelloConfig): string => {
        const logo = config.theme?.logo;
        const siteName = config.site?.title || 'Tixello';

        return `
            <header class="bg-gray-900 text-white sticky top-0 z-50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-20">
                        <a href="/" class="flex items-center">
                            ${logo
                                ? `<img src="${logo}" alt="${siteName}" class="h-10 w-auto">`
                                : `<span class="text-2xl font-extrabold">${siteName}</span>`
                            }
                        </a>
                        <nav class="hidden md:flex items-center space-x-8">
                            <a href="/events" class="text-gray-300 hover:text-white transition font-medium">Evenimente</a>
                            <a href="/blog" class="text-gray-300 hover:text-white transition font-medium">Blog</a>
                            <a href="/cart" class="text-gray-300 hover:text-white transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </a>
                            <a href="/login" class="btn-primary px-6 py-2.5 rounded-full text-sm font-bold">Contul meu</a>
                        </nav>
                        <button class="md:hidden p-2 text-white" id="mobile-menu-btn">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>
        `;
    },

    renderFooter: (config: TixelloConfig): string => {
        const siteName = config.site?.title || 'Tixello';
        const year = new Date().getFullYear();
        const social = config.social || {};

        // Generate social icons HTML
        const socialIcons = [];
        if (social.facebook) {
            socialIcons.push(`<a href="${social.facebook}" target="_blank" class="text-gray-400 hover:text-white transition" aria-label="Facebook"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>`);
        }
        if (social.instagram) {
            socialIcons.push(`<a href="${social.instagram}" target="_blank" class="text-gray-400 hover:text-white transition" aria-label="Instagram"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>`);
        }
        if (social.twitter) {
            socialIcons.push(`<a href="${social.twitter}" target="_blank" class="text-gray-400 hover:text-white transition" aria-label="Twitter"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>`);
        }
        if (social.youtube) {
            socialIcons.push(`<a href="${social.youtube}" target="_blank" class="text-gray-400 hover:text-white transition" aria-label="YouTube"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>`);
        }
        if (social.tiktok) {
            socialIcons.push(`<a href="${social.tiktok}" target="_blank" class="text-gray-400 hover:text-white transition" aria-label="TikTok"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>`);
        }
        if (social.linkedin) {
            socialIcons.push(`<a href="${social.linkedin}" target="_blank" class="text-gray-400 hover:text-white transition" aria-label="LinkedIn"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>`);
        }

        const socialHtml = socialIcons.length > 0
            ? `<div class="flex space-x-4 mt-6">${socialIcons.join('')}</div>`
            : '';

        return `
            <footer class="bg-gray-900 text-white mt-20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                        <div class="col-span-1 md:col-span-2">
                            <h3 class="text-2xl font-extrabold mb-4">${siteName}</h3>
                            <p class="text-gray-400 mb-6">${config.site?.description || ''}</p>
                            ${config.site?.tagline ? `<p class="text-primary font-medium">${config.site.tagline}</p>` : ''}
                            ${socialHtml}
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-6">Navigare</h4>
                            <ul class="space-y-3">
                                <li><a href="/events" class="text-gray-400 hover:text-white transition">Evenimente</a></li>
                                <li><a href="/past-events" class="text-gray-400 hover:text-white transition">Evenimente trecute</a></li>
                                <li><a href="/blog" class="text-gray-400 hover:text-white transition">Blog</a></li>
                                <li><a href="/account" class="text-gray-400 hover:text-white transition">Contul meu</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-6">Legal</h4>
                            <ul class="space-y-3">
                                <li><a href="/terms" class="text-gray-400 hover:text-white transition">Termeni</a></li>
                                <li><a href="/privacy" class="text-gray-400 hover:text-white transition">Confidențialitate</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t border-gray-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center text-sm text-gray-500">
                        <p>&copy; ${year} ${siteName}</p>
                        <div class="mt-4 md:mt-0 flex items-center gap-2">
                            <span>Powered by</span>
                            <a href="${config.platform?.url || 'https://tixello.com'}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center">
                                ${config.platform?.logo_dark
                                    ? `<img src="${config.platform.logo_dark}" alt="${config.platform?.name || 'Tixello'}" class="h-5 w-auto">`
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
TemplateManager.registerTemplate('modern', modernTemplate);

export default modernTemplate;
