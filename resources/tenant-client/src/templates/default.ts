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
                            <a href="/cart" class="text-gray-600 hover:text-primary transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            </a>
                            <a href="/login" class="btn-primary px-4 py-2 rounded-lg text-sm">Contul meu</a>
                        </nav>
                        <button class="md:hidden p-2" id="mobile-menu-btn">
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
            <footer class="bg-gray-50 border-t mt-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                        <div class="col-span-1 md:col-span-2">
                            <h3 class="text-lg font-bold text-gray-900 mb-4">${siteName}</h3>
                            <p class="text-gray-600 text-sm mb-4">${config.site?.description || ''}</p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-4">Linkuri rapide</h4>
                            <ul class="space-y-2 text-sm">
                                <li><a href="/events" class="text-gray-600 hover:text-primary">Evenimente</a></li>
                                <li><a href="/account" class="text-gray-600 hover:text-primary">Contul meu</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-4">Legal</h4>
                            <ul class="space-y-2 text-sm">
                                <li><a href="/terms" class="text-gray-600 hover:text-primary">Termeni și condiții</a></li>
                                <li><a href="/privacy" class="text-gray-600 hover:text-primary">Politica de confidențialitate</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="border-t mt-8 pt-8 text-center text-sm text-gray-500">
                        <p>&copy; ${year} ${siteName}. Toate drepturile rezervate.</p>
                        <p class="mt-2">Powered by <a href="https://tixello.com" class="text-primary hover:underline" target="_blank">Tixello</a></p>
                    </div>
                </div>
            </footer>
        `;
    }
};

// Register the template
TemplateManager.registerTemplate('default', defaultTemplate);

export default defaultTemplate;
