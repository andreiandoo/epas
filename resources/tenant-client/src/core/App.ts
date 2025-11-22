import { ConfigManager, TixelloConfig } from './ConfigManager';
import { ApiClient } from './ApiClient';
import { Router } from './Router';
import { AuthManager } from './AuthManager';
import { EventBus } from './EventBus';
import { ModuleLoader } from './ModuleLoader';

export interface TixelloAppOptions {
    config: TixelloConfig;
    apiClient: ApiClient;
    authManager: AuthManager;
    router: Router;
}

export class TixelloApp {
    private config: TixelloConfig;
    private apiClient: ApiClient;
    private authManager: AuthManager;
    private router: Router;
    private eventBus: EventBus;
    private moduleLoader: ModuleLoader;
    private mountPoint: HTMLElement | null = null;

    constructor(options: TixelloAppOptions) {
        this.config = options.config;
        this.apiClient = options.apiClient;
        this.authManager = options.authManager;
        this.router = options.router;
        this.eventBus = new EventBus();
        this.moduleLoader = new ModuleLoader(this.config.modules);
    }

    async mount(element: HTMLElement): Promise<void> {
        this.mountPoint = element;

        // Load enabled modules
        await this.moduleLoader.loadAll();

        // Setup initial UI
        this.render();

        // Initialize router
        this.router.init();

        // Check authentication
        await this.authManager.checkAuth();

        // Emit ready event
        this.eventBus.emit('app:ready');
    }

    private render(): void {
        if (!this.mountPoint) return;

        this.mountPoint.innerHTML = `
            <div class="min-h-screen flex flex-col bg-gray-50">
                <header class="bg-white shadow-sm sticky top-0 z-50" id="tixello-header">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div class="flex justify-between items-center h-16">
                            <a href="/" class="flex items-center">
                                <span class="text-xl font-bold text-gray-900">Events</span>
                            </a>
                            <nav class="hidden md:flex items-center space-x-8">
                                <a href="/events" class="text-gray-600 hover:text-gray-900 font-medium">Events</a>
                                <a href="/cart" class="text-gray-600 hover:text-gray-900 font-medium flex items-center">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    Cart
                                    <span id="cart-count" class="ml-1 bg-blue-600 text-white text-xs rounded-full px-2 py-0.5 hidden">0</span>
                                </a>
                                <a href="/account" class="text-gray-600 hover:text-gray-900 font-medium">Account</a>
                            </nav>
                            <button id="mobile-menu-btn" class="md:hidden p-2 text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Mobile menu -->
                    <div id="mobile-menu" class="hidden md:hidden border-t">
                        <div class="px-4 py-3 space-y-3">
                            <a href="/events" class="block text-gray-600 hover:text-gray-900 font-medium">Events</a>
                            <a href="/cart" class="block text-gray-600 hover:text-gray-900 font-medium">Cart</a>
                            <a href="/account" class="block text-gray-600 hover:text-gray-900 font-medium">Account</a>
                        </div>
                    </div>
                </header>
                <main class="flex-1" id="tixello-content"></main>
                <footer class="bg-white border-t mt-auto" id="tixello-footer">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div class="flex flex-col md:flex-row justify-between items-center">
                            <p class="text-gray-500 text-sm mb-4 md:mb-0">
                                Powered by <a href="https://tixello.com" target="_blank" class="text-blue-600 hover:text-blue-700" data-external>Tixello</a>
                            </p>
                            <div class="flex space-x-6 text-sm text-gray-500">
                                <a href="/terms" class="hover:text-gray-700">Terms</a>
                                <a href="/privacy" class="hover:text-gray-700">Privacy</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        `;

        // Inject styles
        this.injectStyles();

        // Setup mobile menu toggle
        this.setupMobileMenu();
    }

    private setupMobileMenu(): void {
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        if (btn && menu) {
            btn.addEventListener('click', () => {
                menu.classList.toggle('hidden');
            });
        }
    }

    private injectStyles(): void {
        // Add Tailwind CSS
        const tailwind = document.createElement('script');
        tailwind.src = 'https://cdn.tailwindcss.com';
        document.head.appendChild(tailwind);

        // Add custom styles
        const style = document.createElement('style');
        style.textContent = this.getBaseStyles();
        document.head.appendChild(style);
    }

    private getBaseStyles(): string {
        const theme = this.config.theme;

        return `
            body {
                font-family: ${theme.fontFamily}, system-ui, sans-serif;
            }
            .animate-pulse {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: .5; }
            }
        `;
    }

    // Public API methods
    getConfig(): TixelloConfig {
        return this.config;
    }

    getApiClient(): ApiClient {
        return this.apiClient;
    }

    getAuthManager(): AuthManager {
        return this.authManager;
    }

    getRouter(): Router {
        return this.router;
    }

    on(event: string, handler: Function): void {
        this.eventBus.on(event, handler);
    }

    off(event: string, handler: Function): void {
        this.eventBus.off(event, handler);
    }

    emit(event: string, data?: any): void {
        this.eventBus.emit(event, data);
    }

    navigate(path: string): void {
        this.router.navigate(path);
    }
}
