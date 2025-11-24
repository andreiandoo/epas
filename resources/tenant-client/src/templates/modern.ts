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

        return `
            <footer class="bg-gray-900 text-white mt-20">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                        <div class="col-span-1 md:col-span-2">
                            <h3 class="text-2xl font-extrabold mb-4">${siteName}</h3>
                            <p class="text-gray-400 mb-6">${config.site?.description || ''}</p>
                            ${config.site?.tagline ? `<p class="text-primary font-medium">${config.site.tagline}</p>` : ''}
                        </div>
                        <div>
                            <h4 class="font-bold text-lg mb-6">Navigare</h4>
                            <ul class="space-y-3">
                                <li><a href="/events" class="text-gray-400 hover:text-white transition">Evenimente</a></li>
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
                        <p class="mt-4 md:mt-0">Powered by <a href="https://tixello.com" class="text-primary hover:underline" target="_blank">Tixello</a></p>
                    </div>
                </div>
            </footer>
        `;
    }
};

// Register the template
TemplateManager.registerTemplate('modern', modernTemplate);

export default modernTemplate;
