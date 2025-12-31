import { ApiClient } from './ApiClient';
import { EventBus } from './EventBus';

interface Module {
    name: string;
    init: (apiClient?: ApiClient, eventBus?: EventBus) => Promise<void> | void;
}

export class ModuleLoader {
    private enabledModules: string[];
    private loadedModules: Map<string, Module> = new Map();
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;

    constructor(enabledModules: string[]) {
        this.enabledModules = enabledModules;
    }

    setDependencies(apiClient: ApiClient, eventBus: EventBus): void {
        this.apiClient = apiClient;
        this.eventBus = eventBus;
    }

    async loadAll(): Promise<void> {
        const modulePromises = this.enabledModules.map(name => this.loadModule(name));
        await Promise.all(modulePromises);
    }

    private async loadModule(name: string): Promise<void> {
        try {
            // Dynamic imports based on module name
            let module: Module;

            switch (name) {
                case 'events':
                    const { EventsModule } = await import('../modules/EventsModule');
                    module = new EventsModule();
                    break;
                case 'cart':
                    const { CartModule } = await import('../modules/CartModule');
                    module = new CartModule();
                    break;
                case 'checkout':
                    const { CheckoutModule } = await import('../modules/CheckoutModule');
                    module = new CheckoutModule();
                    break;
                case 'seating':
                    const { SeatingModule } = await import('../modules/SeatingModule');
                    module = new SeatingModule();
                    break;
                case 'shop':
                    const { ShopModule } = await import('../modules/ShopModule');
                    module = new ShopModule();
                    break;
                case 'sleek-client':
                    const { SleekClientModule } = await import('../modules/SleekClientModule');
                    module = new SleekClientModule();
                    break;
                case 'gamification':
                    const { GamificationModule } = await import('../modules/GamificationModule');
                    module = new GamificationModule();
                    break;
                default:
                    return; // Unknown module
            }

            // Pass dependencies to modules that need them
            if (this.apiClient && this.eventBus && (name === 'shop' || name === 'sleek-client' || name === 'gamification')) {
                await module.init(this.apiClient, this.eventBus);
            } else {
                await module.init();
            }
            this.loadedModules.set(name, module);
        } catch (error) {
            console.error(`Failed to load module: ${name}`, error);
        }
    }

    getModule(name: string): Module | undefined {
        return this.loadedModules.get(name);
    }

    isLoaded(name: string): boolean {
        return this.loadedModules.has(name);
    }
}
