interface Module {
    name: string;
    init: () => Promise<void> | void;
}

export class ModuleLoader {
    private enabledModules: string[];
    private loadedModules: Map<string, Module> = new Map();

    constructor(enabledModules: string[]) {
        this.enabledModules = enabledModules;
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
                default:
                    return; // Unknown module
            }

            await module.init();
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
