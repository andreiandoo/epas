import { ConfigManager, TixelloConfig } from './ConfigManager';
import { ApiClient } from './ApiClient';
import { Router } from './Router';
import { AuthManager } from './AuthManager';
import { EventBus } from './EventBus';
import { ModuleLoader } from './ModuleLoader';
import { TemplateManager } from '../templates';

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

        // Use the Router's eventBus for cross-component communication
        const sharedEventBus = this.router.getEventBus();

        // Set dependencies for modules that need them
        this.moduleLoader.setDependencies(this.apiClient, sharedEventBus);

        // Load enabled modules
        await this.moduleLoader.loadAll();

        // Set page meta tags
        this.setMetaTags();

        // Setup initial UI
        this.render();

        // Initialize router
        this.router.init();

        // Check authentication
        await this.authManager.checkAuth();

        // Emit ready event
        this.eventBus.emit('app:ready');
    }

    private setMetaTags(): void {
        // Set document title
        const siteTitle = this.config.site?.title || 'Tixello';
        const tagline = this.config.site?.tagline;
        document.title = tagline ? `${siteTitle} - ${tagline}` : siteTitle;

        // Set meta description
        const description = this.config.site?.description || `Events and tickets by ${siteTitle}`;
        let metaDescription = document.querySelector('meta[name="description"]');
        if (metaDescription) {
            metaDescription.setAttribute('content', description);
        } else {
            metaDescription = document.createElement('meta');
            metaDescription.setAttribute('name', 'description');
            metaDescription.setAttribute('content', description);
            document.head.appendChild(metaDescription);
        }

        // Set Open Graph tags
        this.setMetaTag('og:title', siteTitle);
        this.setMetaTag('og:description', description);
        this.setMetaTag('og:type', 'website');
        if (this.config.theme?.logo) {
            this.setMetaTag('og:image', this.config.theme.logo);
        }
    }

    private setMetaTag(property: string, content: string): void {
        let tag = document.querySelector(`meta[property="${property}"]`);
        if (tag) {
            tag.setAttribute('content', content);
        } else {
            tag = document.createElement('meta');
            tag.setAttribute('property', property);
            tag.setAttribute('content', content);
            document.head.appendChild(tag);
        }
    }

    private render(): void {
        if (!this.mountPoint) return;

        // Use template system for header and footer
        const header = TemplateManager.renderHeader();
        const footer = TemplateManager.renderFooter();

        this.mountPoint.innerHTML = `
            <div class="min-h-screen flex flex-col bg-gray-50">
                ${header}
                <main class="flex-1" id="tixello-content"></main>
                ${footer}
            </div>
        `;

        // Inject styles
        this.injectStyles();

        // Setup mobile menu toggle
        this.setupMobileMenu();
    }

    private setupMobileMenu(): void {
        const menuBtn = document.getElementById('mobile-menu-btn');
        const closeBtn = document.getElementById('mobile-menu-close');
        const overlay = document.getElementById('mobile-menu-overlay');
        const drawer = document.getElementById('mobile-menu-drawer');

        if (!menuBtn || !drawer || !overlay) return;

        const openMenu = () => {
            overlay.classList.remove('hidden');
            requestAnimationFrame(() => {
                overlay.classList.remove('opacity-0');
                drawer.classList.remove('translate-x-full');
            });
            document.body.style.overflow = 'hidden';
        };

        const closeMenu = () => {
            overlay.classList.add('opacity-0');
            drawer.classList.add('translate-x-full');
            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
            document.body.style.overflow = '';
        };

        menuBtn.addEventListener('click', openMenu);
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        overlay.addEventListener('click', closeMenu);

        // Close menu on link click
        drawer.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });
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
