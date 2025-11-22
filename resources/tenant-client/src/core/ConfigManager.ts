export interface TixelloConfig {
    tenantId: number;
    domainId: number;
    domain: string;
    apiEndpoint: string;
    modules: string[];
    theme: ThemeConfig;
    version: string;
    packageHash: string;
}

export interface ThemeConfig {
    primaryColor: string;
    secondaryColor: string;
    logo: string | null;
    favicon: string | null;
    fontFamily: string;
}

export class ConfigManager {
    private static config: TixelloConfig | null = null;

    static async init(encryptedConfig: string): Promise<TixelloConfig> {
        try {
            // Decrypt the configuration
            const decrypted = await this.decrypt(encryptedConfig);
            this.config = JSON.parse(decrypted);

            // Apply theme
            this.applyTheme(this.config!.theme);

            return this.config!;
        } catch (error) {
            throw new Error('Failed to initialize configuration');
        }
    }

    private static async decrypt(encrypted: string): Promise<string> {
        // In production, this would use a proper decryption mechanism
        // For now, we'll decode base64
        try {
            return atob(encrypted);
        } catch {
            // If not base64, assume it's already decrypted (dev mode)
            return encrypted;
        }
    }

    private static applyTheme(theme: ThemeConfig): void {
        // Set CSS custom properties
        const root = document.documentElement;
        root.style.setProperty('--tixello-primary', theme.primaryColor);
        root.style.setProperty('--tixello-secondary', theme.secondaryColor);
        root.style.setProperty('--tixello-font', theme.fontFamily);

        // Update favicon if provided
        if (theme.favicon) {
            const favicon = document.querySelector('link[rel="icon"]') as HTMLLinkElement;
            if (favicon) {
                favicon.href = theme.favicon;
            } else {
                const link = document.createElement('link');
                link.rel = 'icon';
                link.href = theme.favicon;
                document.head.appendChild(link);
            }
        }
    }

    static get(): TixelloConfig {
        if (!this.config) {
            throw new Error('Configuration not initialized');
        }
        return this.config;
    }

    static getApiEndpoint(): string {
        return this.get().apiEndpoint;
    }

    static hasModule(module: string): boolean {
        return this.get().modules.includes(module);
    }
}
