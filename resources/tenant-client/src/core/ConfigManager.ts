export interface TixelloConfig {
    tenantId: number;
    domainId: number;
    domain: string;
    apiEndpoint: string;
    modules: string[];
    theme: ThemeConfig;
    site: SiteConfig;
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

export interface SiteConfig {
    title: string;
    description: string;
    tagline: string;
    language: string;
    template: string;
}

export interface InitAttributesConfig {
    apiEndpoint: string;
    tenantId: number;
    domainId: number;
    domain: string;
}

export interface InitHostnameConfig {
    apiEndpoint: string;
    hostname: string;
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

    static async initFromAttributes(attrs: InitAttributesConfig): Promise<TixelloConfig> {
        // Create default config from attributes
        this.config = {
            tenantId: attrs.tenantId,
            domainId: attrs.domainId,
            domain: attrs.domain,
            apiEndpoint: attrs.apiEndpoint,
            modules: ['core', 'events', 'auth', 'cart', 'checkout'],
            theme: {
                primaryColor: '#3B82F6',
                secondaryColor: '#1E40AF',
                logo: null,
                favicon: null,
                fontFamily: 'Inter',
            },
            site: {
                title: '',
                description: '',
                tagline: '',
                language: 'en',
                template: 'default',
            },
            version: '1.0.0',
            packageHash: '',
        };

        // Fetch full config from API
        try {
            const response = await fetch(`${attrs.apiEndpoint}/config?tenant=${attrs.tenantId}&domain=${attrs.domainId}`);
            if (response.ok) {
                const data = await response.json();
                if (data.theme) {
                    this.config.theme = { ...this.config.theme, ...data.theme };
                }
                if (data.modules) {
                    this.config.modules = data.modules;
                }
                if (data.site) {
                    this.config.site = { ...this.config.site, ...data.site };
                }
            }
        } catch {
            // Use defaults if API fails
        }

        // Apply theme
        this.applyTheme(this.config.theme);

        return this.config;
    }

    static async initFromHostname(attrs: InitHostnameConfig): Promise<TixelloConfig> {
        // Create default config
        this.config = {
            tenantId: 0,
            domainId: 0,
            domain: attrs.hostname,
            apiEndpoint: attrs.apiEndpoint,
            modules: ['core', 'events', 'auth', 'cart', 'checkout'],
            theme: {
                primaryColor: '#3B82F6',
                secondaryColor: '#1E40AF',
                logo: null,
                favicon: null,
                fontFamily: 'Inter',
            },
            site: {
                title: '',
                description: '',
                tagline: '',
                language: 'en',
                template: 'default',
            },
            version: '1.0.0',
            packageHash: '',
        };

        // Fetch config from API using hostname (secure - no IDs exposed)
        try {
            const response = await fetch(`${attrs.apiEndpoint}/config?hostname=${encodeURIComponent(attrs.hostname)}`);
            if (response.ok) {
                const data = await response.json();
                if (data.theme) {
                    this.config.theme = { ...this.config.theme, ...data.theme };
                }
                if (data.modules) {
                    this.config.modules = data.modules;
                }
                if (data.site) {
                    this.config.site = { ...this.config.site, ...data.site };
                }
                if (data.tenant) {
                    this.config.tenantId = data.tenant.id;
                }
            } else {
                throw new Error('Failed to load tenant configuration');
            }
        } catch (error) {
            throw new Error('Failed to initialize configuration from hostname');
        }

        // Apply theme
        this.applyTheme(this.config.theme);

        return this.config;
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

        // Generate darker version for hover states
        const primaryDark = this.darkenColor(theme.primaryColor, 15);
        const secondaryDark = this.darkenColor(theme.secondaryColor, 15);
        root.style.setProperty('--tixello-primary-dark', primaryDark);
        root.style.setProperty('--tixello-secondary-dark', secondaryDark);

        // Inject utility CSS classes
        this.injectThemeStyles(theme);

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

    private static darkenColor(color: string, percent: number): string {
        // Convert hex to RGB, darken, convert back
        const hex = color.replace('#', '');
        const r = parseInt(hex.substring(0, 2), 16);
        const g = parseInt(hex.substring(2, 4), 16);
        const b = parseInt(hex.substring(4, 6), 16);

        const darken = (value: number) => Math.max(0, Math.floor(value * (1 - percent / 100)));

        const newR = darken(r).toString(16).padStart(2, '0');
        const newG = darken(g).toString(16).padStart(2, '0');
        const newB = darken(b).toString(16).padStart(2, '0');

        return `#${newR}${newG}${newB}`;
    }

    private static injectThemeStyles(theme: ThemeConfig): void {
        // Remove existing theme styles
        const existingStyle = document.getElementById('tixello-theme-styles');
        if (existingStyle) {
            existingStyle.remove();
        }

        // Create style element with utility classes
        const style = document.createElement('style');
        style.id = 'tixello-theme-styles';
        style.textContent = `
            /* Primary color utilities */
            .bg-primary { background-color: var(--tixello-primary) !important; }
            .bg-primary-dark { background-color: var(--tixello-primary-dark) !important; }
            .text-primary { color: var(--tixello-primary) !important; }
            .border-primary { border-color: var(--tixello-primary) !important; }

            /* Secondary color utilities */
            .bg-secondary { background-color: var(--tixello-secondary) !important; }
            .bg-secondary-dark { background-color: var(--tixello-secondary-dark) !important; }
            .text-secondary { color: var(--tixello-secondary) !important; }
            .border-secondary { border-color: var(--tixello-secondary) !important; }

            /* Gradient utilities */
            .from-primary { --tw-gradient-from: var(--tixello-primary); }
            .to-secondary { --tw-gradient-to: var(--tixello-secondary); }

            /* Button styles */
            .btn-primary {
                background-color: var(--tixello-primary);
                color: white;
                transition: background-color 0.2s;
            }
            .btn-primary:hover {
                background-color: var(--tixello-primary-dark);
            }

            .btn-secondary {
                background-color: var(--tixello-secondary);
                color: white;
                transition: background-color 0.2s;
            }
            .btn-secondary:hover {
                background-color: var(--tixello-secondary-dark);
            }

            /* Hover utilities */
            .hover\\:text-primary:hover { color: var(--tixello-primary) !important; }
            .hover\\:bg-primary:hover { background-color: var(--tixello-primary) !important; }
            .hover\\:bg-primary-dark:hover { background-color: var(--tixello-primary-dark) !important; }

            /* Font family */
            body {
                font-family: var(--tixello-font), system-ui, sans-serif;
            }

            /* Ring color for focus states */
            .focus\\:ring-primary:focus {
                --tw-ring-color: var(--tixello-primary);
            }
        `;
        document.head.appendChild(style);
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
