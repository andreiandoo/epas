export interface TixelloConfig {
    tenantId: number;
    domainId: number;
    domain: string;
    apiEndpoint: string;
    modules: string[];
    theme: ThemeConfig;
    site: SiteConfig;
    social: SocialConfig;
    menus: MenusConfig;
    version: string;
    packageHash: string;
}

export interface MenusConfig {
    header: MenuItem[];
    footer: MenuItem[];
}

export interface MenuItem {
    title: string;
    slug: string;
    url: string;
}

export interface SocialConfig {
    facebook: string | null;
    instagram: string | null;
    twitter: string | null;
    youtube: string | null;
    tiktok: string | null;
    linkedin: string | null;
}

export interface ThemeConfig {
    primaryColor: string;
    secondaryColor: string;
    logo: string | null;
    favicon: string | null;
    fontFamily: string;
    // Extended theme config for visual editor
    colors?: ThemeColors;
    typography?: ThemeTypography;
    spacing?: ThemeSpacing;
    borders?: ThemeBorders;
    shadows?: ThemeShadows;
    header?: ThemeHeader;
    buttons?: ThemeButtons;
}

export interface ThemeColors {
    primary: string;
    primaryHover: string;
    secondary: string;
    secondaryHover: string;
    accent: string;
    background: string;
    surface: string;
    text: string;
    textMuted: string;
    border: string;
    success: string;
    warning: string;
    error: string;
}

export interface ThemeTypography {
    fontFamily: string;
    headingFont: string;
    baseFontSize: string;
    headingWeight: string;
    bodyWeight: string;
    lineHeight: string;
}

export interface ThemeSpacing {
    containerWidth: string;
    sectionPadding: string;
    cardPadding: string;
    gridGap: string;
}

export interface ThemeBorders {
    radius: string;
    radiusLg: string;
    width: string;
}

export interface ThemeShadows {
    card: string;
    cardHover: string;
    button: string;
    dropdown: string;
}

export interface ThemeHeader {
    background: string;
    textColor: string;
    height: string;
    sticky: boolean;
    logoMaxHeight: string;
}

export interface ThemeButtons {
    primaryBg: string;
    primaryText: string;
    primaryHoverBg: string;
    secondaryBg: string;
    secondaryText: string;
    secondaryHoverBg: string;
    borderRadius: string;
    paddingX: string;
    paddingY: string;
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
            const baseConfig = JSON.parse(decrypted);

            // Set base config with defaults for missing fields
            this.config = {
                ...baseConfig,
                site: baseConfig.site || {
                    title: '',
                    description: '',
                    tagline: '',
                    language: 'en',
                    template: 'default',
                },
                social: baseConfig.social || {
                    facebook: null,
                    instagram: null,
                    twitter: null,
                    youtube: null,
                    tiktok: null,
                    linkedin: null,
                },
                menus: baseConfig.menus || {
                    header: [],
                    footer: [],
                },
            };

            // Fetch full config from API to get latest data
            try {
                const url = `${this.config.apiEndpoint}/config?hostname=${encodeURIComponent(this.config.domain)}`;
                const response = await fetch(url);
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
                    if (data.social) {
                        this.config.social = { ...this.config.social, ...data.social };
                    }
                    if (data.menus) {
                        this.config.menus = data.menus;
                    }
                }
            } catch {
                // Use baked config if API fails
            }

            // Apply theme
            this.applyTheme(this.config.theme);

            return this.config;
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
            social: {
                facebook: null,
                instagram: null,
                twitter: null,
                youtube: null,
                tiktok: null,
                linkedin: null,
            },
            menus: {
                header: [],
                footer: [],
            },
            version: '1.2.0',
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
                if (data.social) {
                    this.config.social = { ...this.config.social, ...data.social };
                }
                if (data.menus) {
                    this.config.menus = data.menus;
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
            social: {
                facebook: null,
                instagram: null,
                twitter: null,
                youtube: null,
                tiktok: null,
                linkedin: null,
            },
            menus: {
                header: [],
                footer: [],
            },
            version: '1.1.0',
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
                if (data.social) {
                    this.config.social = { ...this.config.social, ...data.social };
                }
                if (data.menus) {
                    this.config.menus = data.menus;
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
        const root = document.documentElement;

        // Basic theme properties (backwards compatible)
        root.style.setProperty('--tixello-primary', theme.primaryColor);
        root.style.setProperty('--tixello-secondary', theme.secondaryColor);
        root.style.setProperty('--tixello-font', theme.fontFamily);

        // Generate darker version for hover states
        const primaryDark = this.darkenColor(theme.primaryColor, 15);
        const secondaryDark = this.darkenColor(theme.secondaryColor, 15);
        root.style.setProperty('--tixello-primary-dark', primaryDark);
        root.style.setProperty('--tixello-secondary-dark', secondaryDark);

        // Extended colors
        if (theme.colors) {
            root.style.setProperty('--theme-primary', theme.colors.primary);
            root.style.setProperty('--theme-primary-hover', theme.colors.primaryHover);
            root.style.setProperty('--theme-secondary', theme.colors.secondary);
            root.style.setProperty('--theme-secondary-hover', theme.colors.secondaryHover);
            root.style.setProperty('--theme-accent', theme.colors.accent);
            root.style.setProperty('--theme-background', theme.colors.background);
            root.style.setProperty('--theme-surface', theme.colors.surface);
            root.style.setProperty('--theme-text', theme.colors.text);
            root.style.setProperty('--theme-text-muted', theme.colors.textMuted);
            root.style.setProperty('--theme-border', theme.colors.border);
            root.style.setProperty('--theme-success', theme.colors.success);
            root.style.setProperty('--theme-warning', theme.colors.warning);
            root.style.setProperty('--theme-error', theme.colors.error);
        }

        // Typography
        if (theme.typography) {
            root.style.setProperty('--theme-font-family', theme.typography.fontFamily);
            root.style.setProperty('--theme-heading-font', theme.typography.headingFont);
            root.style.setProperty('--theme-font-size-base', theme.typography.baseFontSize);
            root.style.setProperty('--theme-heading-weight', theme.typography.headingWeight);
            root.style.setProperty('--theme-body-weight', theme.typography.bodyWeight);
            root.style.setProperty('--theme-line-height', theme.typography.lineHeight);
        }

        // Spacing
        if (theme.spacing) {
            root.style.setProperty('--theme-container-width', theme.spacing.containerWidth);
            root.style.setProperty('--theme-section-padding', theme.spacing.sectionPadding);
            root.style.setProperty('--theme-card-padding', theme.spacing.cardPadding);
            root.style.setProperty('--theme-grid-gap', theme.spacing.gridGap);
        }

        // Borders
        if (theme.borders) {
            root.style.setProperty('--theme-border-radius', theme.borders.radius);
            root.style.setProperty('--theme-border-radius-lg', theme.borders.radiusLg);
            root.style.setProperty('--theme-border-width', theme.borders.width);
        }

        // Shadows
        if (theme.shadows) {
            root.style.setProperty('--theme-shadow-card', theme.shadows.card);
            root.style.setProperty('--theme-shadow-card-hover', theme.shadows.cardHover);
            root.style.setProperty('--theme-shadow-button', theme.shadows.button);
            root.style.setProperty('--theme-shadow-dropdown', theme.shadows.dropdown);
        }

        // Header
        if (theme.header) {
            root.style.setProperty('--theme-header-bg', theme.header.background);
            root.style.setProperty('--theme-header-text', theme.header.textColor);
            root.style.setProperty('--theme-header-height', theme.header.height);
            root.style.setProperty('--theme-logo-max-height', theme.header.logoMaxHeight);
        }

        // Buttons
        if (theme.buttons) {
            root.style.setProperty('--theme-btn-primary-bg', theme.buttons.primaryBg);
            root.style.setProperty('--theme-btn-primary-text', theme.buttons.primaryText);
            root.style.setProperty('--theme-btn-primary-hover', theme.buttons.primaryHoverBg);
            root.style.setProperty('--theme-btn-secondary-bg', theme.buttons.secondaryBg);
            root.style.setProperty('--theme-btn-secondary-text', theme.buttons.secondaryText);
            root.style.setProperty('--theme-btn-secondary-hover', theme.buttons.secondaryHoverBg);
            root.style.setProperty('--theme-btn-radius', theme.buttons.borderRadius);
            root.style.setProperty('--theme-btn-padding-x', theme.buttons.paddingX);
            root.style.setProperty('--theme-btn-padding-y', theme.buttons.paddingY);
        }

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

    /**
     * Update theme configuration in real-time (for preview mode)
     */
    static updateTheme(partialTheme: Partial<ThemeConfig>): void {
        if (!this.config) {
            console.warn('[ConfigManager] Cannot update theme: config not initialized');
            return;
        }

        // Deep merge the theme
        this.config.theme = this.deepMerge(this.config.theme, partialTheme) as ThemeConfig;

        // Re-apply the theme
        this.applyTheme(this.config.theme);

        console.log('[ConfigManager] Theme updated:', partialTheme);
    }

    /**
     * Deep merge two objects
     */
    private static deepMerge(target: any, source: any): any {
        const result = { ...target };

        for (const key in source) {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                result[key] = this.deepMerge(target[key] || {}, source[key]);
            } else {
                result[key] = source[key];
            }
        }

        return result;
    }

    /**
     * Get current theme config
     */
    static getTheme(): ThemeConfig {
        return this.get().theme;
    }
}
