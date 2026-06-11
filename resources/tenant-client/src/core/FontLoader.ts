interface FontDefinition {
    name: string;
    category: 'sans-serif' | 'serif' | 'display';
    weights: number[];
    url?: string; // For custom font URL
}

export const AVAILABLE_FONTS: Record<string, FontDefinition> = {
    // Sans-serif
    'inter': { name: 'Inter', category: 'sans-serif', weights: [400, 500, 600, 700, 800] },
    'poppins': { name: 'Poppins', category: 'sans-serif', weights: [400, 500, 600, 700] },
    'open-sans': { name: 'Open Sans', category: 'sans-serif', weights: [400, 600, 700] },
    'roboto': { name: 'Roboto', category: 'sans-serif', weights: [400, 500, 700] },
    'montserrat': { name: 'Montserrat', category: 'sans-serif', weights: [400, 500, 600, 700] },
    'lato': { name: 'Lato', category: 'sans-serif', weights: [400, 700] },
    // Serif
    'playfair-display': { name: 'Playfair Display', category: 'serif', weights: [400, 700] },
    'merriweather': { name: 'Merriweather', category: 'serif', weights: [400, 700] },
    'lora': { name: 'Lora', category: 'serif', weights: [400, 600, 700] },
    // Display
    'oswald': { name: 'Oswald', category: 'sans-serif', weights: [400, 500, 600, 700] },
    'raleway': { name: 'Raleway', category: 'sans-serif', weights: [400, 500, 600, 700] },
    'bebas-neue': { name: 'Bebas Neue', category: 'sans-serif', weights: [400] },
};

export class FontLoader {
    private static loadedFonts: Set<string> = new Set();
    private static loadingPromises: Map<string, Promise<void>> = new Map();

    /**
     * Load a font by its key
     */
    static async load(fontKey: string): Promise<void> {
        // Already loaded
        if (this.loadedFonts.has(fontKey)) {
            return;
        }

        // Currently loading
        if (this.loadingPromises.has(fontKey)) {
            return this.loadingPromises.get(fontKey);
        }

        const fontDef = AVAILABLE_FONTS[fontKey];
        if (!fontDef) {
            console.warn(`[FontLoader] Unknown font: ${fontKey}`);
            return;
        }

        const loadPromise = this.loadFromBunny(fontKey, fontDef);
        this.loadingPromises.set(fontKey, loadPromise);

        try {
            await loadPromise;
            this.loadedFonts.add(fontKey);
        } finally {
            this.loadingPromises.delete(fontKey);
        }
    }

    /**
     * Load font from Bunny Fonts (privacy-friendly Google Fonts alternative)
     */
    private static async loadFromBunny(fontKey: string, fontDef: FontDefinition): Promise<void> {
        const fontName = fontDef.name.replace(/ /g, '+');
        const weights = fontDef.weights.join(';');

        // Bunny Fonts URL (GDPR compliant Google Fonts proxy)
        const url = `https://fonts.bunny.net/css?family=${fontName}:wght@${weights}&display=swap`;

        return new Promise((resolve, reject) => {
            // Check if already loaded via link tag
            const existingLink = document.querySelector(`link[href*="${fontName}"]`);
            if (existingLink) {
                resolve();
                return;
            }

            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = url;

            link.onload = () => {
                console.log(`[FontLoader] Loaded: ${fontDef.name}`);
                resolve();
            };

            link.onerror = () => {
                console.error(`[FontLoader] Failed to load: ${fontDef.name}`);
                reject(new Error(`Failed to load font: ${fontDef.name}`));
            };

            document.head.appendChild(link);
        });
    }

    /**
     * Load multiple fonts
     */
    static async loadMultiple(fontKeys: string[]): Promise<void> {
        const uniqueKeys = [...new Set(fontKeys)];
        await Promise.all(uniqueKeys.map(key => this.load(key)));
    }

    /**
     * Get font CSS value with fallbacks
     */
    static getFontStack(fontKey: string): string {
        const fontDef = AVAILABLE_FONTS[fontKey];
        if (!fontDef) {
            return 'system-ui, sans-serif';
        }

        const fallback = fontDef.category === 'serif'
            ? 'Georgia, serif'
            : 'system-ui, -apple-system, sans-serif';

        return `"${fontDef.name}", ${fallback}`;
    }

    /**
     * Check if font is loaded
     */
    static isLoaded(fontKey: string): boolean {
        return this.loadedFonts.has(fontKey);
    }

    /**
     * Get all available fonts
     */
    static getAvailable(): Record<string, FontDefinition> {
        return AVAILABLE_FONTS;
    }

    /**
     * Preload fonts that will likely be used
     */
    static preloadCommon(): void {
        // Preload Inter as it's the default
        this.load('inter').catch(() => {});
    }
}
