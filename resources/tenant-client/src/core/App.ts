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
            <div class="tixello-container">
                <header class="tixello-header" id="tixello-header"></header>
                <main class="tixello-main" id="tixello-content"></main>
                <footer class="tixello-footer" id="tixello-footer"></footer>
            </div>
        `;

        // Inject styles
        this.injectStyles();
    }

    private injectStyles(): void {
        const style = document.createElement('style');
        style.textContent = this.getBaseStyles();
        document.head.appendChild(style);
    }

    private getBaseStyles(): string {
        const theme = this.config.theme;

        return `
            .tixello-container {
                font-family: ${theme.fontFamily}, system-ui, sans-serif;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }
            .tixello-header {
                background: ${theme.primaryColor};
                color: white;
                padding: 1rem;
            }
            .tixello-main {
                flex: 1;
                padding: 1rem;
            }
            .tixello-footer {
                background: #f3f4f6;
                padding: 1rem;
                text-align: center;
                font-size: 0.875rem;
                color: #6b7280;
            }
            .tixello-btn {
                background: ${theme.primaryColor};
                color: white;
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 0.375rem;
                cursor: pointer;
                font-weight: 500;
            }
            .tixello-btn:hover {
                background: ${theme.secondaryColor};
            }
            .tixello-card {
                background: white;
                border-radius: 0.5rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                padding: 1rem;
                margin-bottom: 1rem;
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
