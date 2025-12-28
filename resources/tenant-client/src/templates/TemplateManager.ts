import { TixelloConfig } from '../core/ConfigManager';

export interface TemplateConfig {
    name: string;
    // Layout components
    headerClass: string;
    footerClass: string;
    containerClass: string;
    // Hero section
    heroClass: string;
    heroTitleClass: string;
    heroSubtitleClass: string;
    // Cards
    cardClass: string;
    cardHoverClass: string;
    // Buttons
    primaryButtonClass: string;
    secondaryButtonClass: string;
    // Typography
    headingClass: string;
    subheadingClass: string;
    // Render functions
    renderHeader: (config: TixelloConfig) => string;
    renderFooter: (config: TixelloConfig) => string;
}

// Template registry
const templates: Record<string, TemplateConfig> = {};

export class TemplateManager {
    private static currentTemplate: TemplateConfig | null = null;
    private static config: TixelloConfig | null = null;

    static registerTemplate(name: string, template: TemplateConfig): void {
        templates[name] = template;
    }

    static init(config: TixelloConfig): void {
        this.config = config;
        const templateName = config.site?.template || 'default';
        this.currentTemplate = templates[templateName] || templates['default'];

        if (!this.currentTemplate) {
            console.warn(`Template "${templateName}" not found, using default`);
            this.currentTemplate = templates['default'];
        }
    }

    static get(): TemplateConfig {
        if (!this.currentTemplate) {
            throw new Error('TemplateManager not initialized');
        }
        return this.currentTemplate;
    }

    static getConfig(): TixelloConfig {
        if (!this.config) {
            throw new Error('TemplateManager not initialized');
        }
        return this.config;
    }

    static renderHeader(): string {
        return this.get().renderHeader(this.getConfig());
    }

    static renderFooter(): string {
        return this.get().renderFooter(this.getConfig());
    }

    static getAvailableTemplates(): string[] {
        return Object.keys(templates);
    }
}
